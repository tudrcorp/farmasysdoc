<?php

use App\Enums\InventoryMovementType;
use App\Enums\PartnerAffiliateSource;
use App\Enums\PartnerAgreementSaleStatus;
use App\Enums\PartnerFundMovementType;
use App\Enums\PartnerPanelPermission;
use App\Models\Branch;
use App\Models\Client;
use App\Models\Inventory;
use App\Models\InventoryMovement;
use App\Models\PartnerAgreementSale;
use App\Models\PartnerCompanyPlan;
use App\Models\PartnerPlanAffiliate;
use App\Models\Plan;
use App\Models\Product;
use App\Models\User;
use App\Services\Partners\PartnerAffiliateService;
use App\Services\Partners\PartnerAgreementSaleService;
use App\Services\Partners\PartnerDashboardMetrics;
use App\Services\Partners\PartnerFundLedger;
use Filament\Facades\Filament;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->branch = Branch::factory()->create();
    $this->actor = User::factory()->create(['roles' => ['ADMINISTRADOR'], 'branch_id' => $this->branch->getKey()]);
    $this->assignment = PartnerCompanyPlan::factory()->withConsumptionFund(100.0)->create([
        'plan_id' => Plan::factory()->consumptionFund()->active(),
    ]);
    $this->client = Client::factory()->create(['document_number' => '12345678']);
    $this->affiliate = app(PartnerAffiliateService::class)->enroll(
        $this->assignment,
        $this->client,
        PartnerAffiliateSource::Counter,
        $this->actor,
        'Ana Pérez (RR. HH.)',
    );
    $this->product = Product::factory()->create(['sale_price' => 10, 'cost_price' => 6, 'applies_vat' => false, 'requires_expiry_on_purchase' => false]);
    $this->inventory = Inventory::factory()->create(['branch_id' => $this->branch->getKey(), 'product_id' => $this->product->getKey(), 'quantity' => 20]);
});

function registerAgreementSale(float $quantity = 2): PartnerAgreementSale
{
    return app(PartnerAgreementSaleService::class)->register(
        test()->branch->getKey(),
        test()->affiliate->getKey(),
        [['product_id' => test()->product->getKey(), 'quantity' => $quantity]],
        test()->actor,
        40.0,
    );
}

describe('registering', function (): void {
    test('a convenio sale discounts inventory and the fund, and numbers the delivery note', function (): void {
        $sale = registerAgreementSale(2);

        expect($sale->number)->toBe(PartnerAgreementSale::numberFor($sale->getKey()))
            ->and($sale->status)->toBe(PartnerAgreementSaleStatus::Completed)
            ->and((float) $sale->total)->toBeGreaterThan(0)
            ->and($sale->client_name)->toBe($this->client->name)
            ->and($sale->items)->toHaveCount(1)
            ->and((float) $this->inventory->fresh()->quantity)->toBe(18.0)
            ->and($this->assignment->fresh()->fundAvailableUsd())->toBe(round(100 - (float) $sale->total, 2))
            ->and((float) $sale->fund_balance_after_usd)->toBe(round(100 - (float) $sale->total, 2));

        expect(InventoryMovement::query()
            ->where('reference_type', PartnerAgreementSale::class)
            ->where('reference_id', $sale->getKey())
            ->where('movement_type', InventoryMovementType::Sale)
            ->exists())->toBeTrue();

        expect($sale->assignment->fundMovements()->where('type', PartnerFundMovementType::Consumption->value)->sum('amount_usd'))
            ->toEqual(-1 * (float) $sale->total);
    });

    test('it is blocked when the fund does not cover the sale', function (): void {
        expect(fn () => registerAgreementSale(1000))->toThrow(ValidationException::class);

        expect(PartnerAgreementSale::query()->count())->toBe(0)
            ->and((float) $this->inventory->fresh()->quantity)->toBe(20.0);
    });

    test('an inactive affiliate or an inactive plan cannot buy', function (): void {
        app(PartnerAffiliateService::class)->setActive($this->affiliate, false);

        expect(fn () => registerAgreementSale())->toThrow(ValidationException::class);

        app(PartnerAffiliateService::class)->setActive($this->affiliate->fresh(), true);
        $this->assignment->plan->update(['status' => 'inactivo']);

        expect(fn () => registerAgreementSale())->toThrow(ValidationException::class);
    });

    test('recharges add to the available fund', function (): void {
        app(PartnerFundLedger::class)->recharge($this->assignment, 50, $this->actor, 'Recarga de prueba');

        expect($this->assignment->fresh()->fundAvailableUsd())->toBe(150.0);
    });
});

describe('cancelling', function (): void {
    test('cancelling returns inventory and the amount to the fund, only once', function (): void {
        $sale = registerAgreementSale(3);
        $service = app(PartnerAgreementSaleService::class);

        $service->cancel($sale, $this->actor, 'El afiliado devolvió los productos');

        expect($sale->fresh()->status)->toBe(PartnerAgreementSaleStatus::Cancelled)
            ->and((float) $this->inventory->fresh()->quantity)->toBe(20.0)
            ->and($this->assignment->fresh()->fundAvailableUsd())->toBe(100.0)
            ->and(fn () => $service->cancel($sale->fresh(), $this->actor, 'otra vez'))->toThrow(ValidationException::class);
    });
});

describe('affiliates', function (): void {
    test('enrolling is idempotent and counter enrollments require who validated', function (): void {
        $service = app(PartnerAffiliateService::class);

        $again = $service->enroll($this->assignment, $this->client, PartnerAffiliateSource::Counter, $this->actor, 'Otra persona');

        expect($again->getKey())->toBe($this->affiliate->getKey())
            ->and(PartnerPlanAffiliate::query()->count())->toBe(1)
            ->and(fn () => $service->enroll($this->assignment, Client::factory()->create(), PartnerAffiliateSource::Counter, $this->actor))
            ->toThrow(ValidationException::class);
    });

    test('the import reads a semicolon CSV, reuses clients by document and does not duplicate', function (): void {
        $path = tempnam(sys_get_temp_dir(), 'afil').'.csv';
        file_put_contents($path, "Cédula;Nombre;Teléfono;Correo\nV-12.345.678;Cliente Existente;0414;\nV-87654321;maria nueva;04120000000;maria@example.com\nV-87654321;maria nueva;;\n;Sin cédula;;\n");

        $service = app(PartnerAffiliateService::class);
        $parsed = $service->parseImportFile($path);

        expect($parsed['rows'])->toHaveCount(2)
            ->and($parsed['duplicates'])->toBe(1)
            ->and($parsed['errors'])->toHaveCount(1);

        $result = $service->import($this->assignment, $parsed['rows'], $this->actor);

        expect($result)->toBe(['enrolled' => 1, 'already' => 1, 'created_clients' => 1])
            ->and(Client::query()->where('document_number', '87654321')->value('name'))->toBe('Maria Nueva')
            ->and($service->import($this->assignment, $parsed['rows'], $this->actor))->toBe(['enrolled' => 0, 'already' => 2, 'created_clients' => 0]);

        @unlink($path);
    });

    test('the cashier only sees Flexible convenios of active plans for the client', function (): void {
        $options = app(PartnerAffiliateService::class)->flexibleOptionsForClient($this->client->getKey());

        expect($options)->toHaveCount(1)
            ->and($options[0]['affiliate_id'])->toBe($this->affiliate->getKey())
            ->and($options[0]['available'])->toBe(100.0);
    });
});

describe('partner panel', function (): void {
    beforeEach(function (): void {
        Filament::setCurrentPanel(User::PARTNER_PANEL_ID);
        $this->partnerUser = User::factory()->partnerPanelUser($this->assignment->partnerCompany, [PartnerPanelPermission::Account, PartnerPanelPermission::PlansAndAgreements])->create();
    });

    test('the partner sees its consumptions with detail and its affiliates, never another partner\'s', function (): void {
        $sale = registerAgreementSale();

        $otherAssignment = PartnerCompanyPlan::factory()->withConsumptionFund(100.0)->create(['plan_id' => Plan::factory()->consumptionFund()->active()]);
        $otherAffiliate = app(PartnerAffiliateService::class)->enroll($otherAssignment, Client::factory()->create(), PartnerAffiliateSource::Import, $this->actor);
        $otherSale = app(PartnerAgreementSaleService::class)->register($this->branch->getKey(), $otherAffiliate->getKey(), [['product_id' => $this->product->getKey(), 'quantity' => 1]], $this->actor);

        $this->actingAs($this->partnerUser);
        $this->get('/business-partners/consumos')->assertOk()->assertSee($sale->number)->assertDontSee($otherSale->number);
        $this->get("/business-partners/consumos/{$sale->getKey()}")->assertOk()->assertSee($this->product->name);
        $this->get("/business-partners/consumos/{$otherSale->getKey()}")->assertNotFound();
        $this->get('/business-partners/afiliados')->assertOk()->assertSee($this->client->name);
    });

    test('the dashboard fund comes from the ledger', function (): void {
        $sale = registerAgreementSale();

        $fund = app(PartnerDashboardMetrics::class)->for((int) $this->assignment->partner_company_id)['fund'];

        expect($fund['authorized'])->toBe(100.0)
            ->and($fund['consumed'])->toBe((float) $sale->total)
            ->and($fund['remaining'])->toBe(round(100 - (float) $sale->total, 2));
    });
});

describe('farmaadmin', function (): void {
    beforeEach(fn () => Filament::setCurrentPanel('farmaadmin'));

    test('staff open the delivery note page and its PDF', function (): void {
        $sale = registerAgreementSale();

        $this->actingAs($this->actor);
        $this->get("/farmaadmin/partner-agreement-sales/{$sale->getKey()}")->assertOk()->assertSee($sale->number);
        $this->get(route('partner-agreement-sales.delivery-note', $sale))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    });
});
