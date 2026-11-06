<?php

use App\Enums\PartnerCompanyDocumentType;
use App\Enums\PartnerCompanyPaymentModality;
use App\Filament\Resources\PartnerCompanies\Pages\CreatePartnerCompany;
use App\Filament\Resources\PartnerCompanies\Pages\ListPartnerCompanies;
use App\Filament\Resources\PartnerCompanies\Pages\ViewPartnerCompany;
use App\Filament\Resources\PartnerCompanies\PartnerCompanyResource;
use App\Models\Branch;
use App\Models\Country;
use App\Models\PartnerCompany;
use App\Models\PartnerCompanyContact;
use App\Models\PartnerCompanyDocument;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function (): void {
    Filament::setCurrentPanel('farmaadmin');
    Country::query()->create(['name' => 'VENEZUELA', 'code' => 'VE']);
    $this->admin = User::factory()->create(['roles' => ['ADMINISTRADOR']]);
});

test('creating a credit partner stores branches, limit and starts the balance at the limit', function (): void {
    $branches = Branch::factory()->count(2)->create();

    Livewire::actingAs($this->admin)
        ->test(CreatePartnerCompany::class)
        ->fillForm([
            'legal_name' => 'Aliado Salud C.A.',
            'tax_id' => 'J123456789',
            'client_type' => 'centro-salud',
            'branches' => $branches->pluck('id')->all(),
            'payment_modality' => PartnerCompanyPaymentModality::Integral->value,
            'credit_limit_usd' => 1500,
            'country' => 'VENEZUELA',
            'date_created' => now()->toDateString(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $partner = PartnerCompany::query()->where('tax_id', 'J123456789')->firstOrFail();

    expect($partner->payment_modality)->toBe(PartnerCompanyPaymentModality::Integral)
        ->and((float) $partner->credit_limit_usd)->toBe(1500.0)
        ->and((float) $partner->assigned_credit_limit)->toBe(1500.0)
        ->and($partner->branches()->count())->toBe(2);
});

test('the available balance cannot exceed the approved credit limit', function (): void {
    $branch = Branch::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(CreatePartnerCompany::class)
        ->fillForm([
            'legal_name' => 'Aliado Exceso C.A.',
            'tax_id' => 'J111111111',
            'client_type' => 'empresa',
            'branches' => [$branch->id],
            'payment_modality' => PartnerCompanyPaymentModality::Credit->value,
            'credit_limit_usd' => 100,
            'assigned_credit_limit' => 200,
            'country' => 'VENEZUELA',
            'date_created' => now()->toDateString(),
        ])
        ->call('create')
        ->assertHasFormErrors(['assigned_credit_limit' => 'lte']);
});

test('cash partners do not keep a credit limit or balance', function (): void {
    $branch = Branch::factory()->create();

    Livewire::actingAs($this->admin)
        ->test(CreatePartnerCompany::class)
        ->fillForm([
            'legal_name' => 'Aliado Contado C.A.',
            'tax_id' => 'J222222222',
            'client_type' => 'farmacia',
            'branches' => [$branch->id],
            'payment_modality' => PartnerCompanyPaymentModality::Cash->value,
            'country' => 'VENEZUELA',
            'date_created' => now()->toDateString(),
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $partner = PartnerCompany::query()->where('tax_id', 'J222222222')->firstOrFail();

    expect($partner->credit_limit_usd)->toBeNull()
        ->and($partner->assigned_credit_limit)->toBeNull();
});

test('the list filters partners by branch, client type and negotiation agreement', function (): void {
    [$branchA, $branchB] = Branch::factory()->count(2)->create()->all();

    $creditPartner = PartnerCompany::factory()->onCredit(800)->create(['client_type' => 'aseguradora']);
    $creditPartner->branches()->attach($branchA);

    $cashPartner = PartnerCompany::factory()->create(['client_type' => 'empresa']);
    $cashPartner->branches()->attach($branchB);

    Livewire::actingAs($this->admin)
        ->test(ListPartnerCompanies::class)
        ->assertCanSeeTableRecords([$creditPartner, $cashPartner])
        ->filterTable('branches', [$branchA->id])
        ->assertCanSeeTableRecords([$creditPartner])
        ->assertCanNotSeeTableRecords([$cashPartner])
        ->resetTableFilters()
        ->filterTable('client_type', ['empresa'])
        ->assertCanSeeTableRecords([$cashPartner])
        ->assertCanNotSeeTableRecords([$creditPartner])
        ->resetTableFilters()
        ->filterTable('payment_modality', [PartnerCompanyPaymentModality::Credit->value])
        ->assertCanSeeTableRecords([$creditPartner])
        ->assertCanNotSeeTableRecords([$cashPartner]);
});

test('non administrators only see partners of their branches or without branch', function (): void {
    [$ownBranch, $otherBranch] = Branch::factory()->count(2)->create()->all();
    $manager = User::factory()->create(['roles' => ['GERENCIA'], 'branch_id' => $ownBranch->id]);

    $ownPartner = PartnerCompany::factory()->create();
    $ownPartner->branches()->attach($ownBranch);

    $otherPartner = PartnerCompany::factory()->create();
    $otherPartner->branches()->attach($otherBranch);

    $legacyPartner = PartnerCompany::factory()->create();

    $this->actingAs($manager);

    $visibleIds = PartnerCompanyResource::getEloquentQuery()->pluck('id')->all();

    expect($visibleIds)->toContain($ownPartner->id, $legacyPartner->id)
        ->not->toContain($otherPartner->id);
});

test('the view page shows contacts and documents', function (): void {
    $partner = PartnerCompany::factory()->create();
    PartnerCompanyContact::factory()->responsible()->create([
        'partner_company_id' => $partner->id,
        'name' => 'María Rodríguez',
    ]);
    PartnerCompanyDocument::factory()->create([
        'partner_company_id' => $partner->id,
        'type' => PartnerCompanyDocumentType::CommercialRegistry,
        'original_name' => 'registro-mercantil.pdf',
    ]);

    Livewire::actingAs($this->admin)
        ->test(ViewPartnerCompany::class, ['record' => $partner->getKey()])
        ->assertSuccessful()
        ->assertSee('María Rodríguez')
        ->assertSee('Responsable')
        ->assertSee('Registro Mercantil')
        ->assertSee('registro-mercantil.pdf');
});

test('partner documents are downloadable only by users with access to partner companies', function (): void {
    Storage::fake(PartnerCompanyDocument::DISK);
    Storage::disk(PartnerCompanyDocument::DISK)->put('partner-companies/documents/rif.pdf', 'pdf');

    $document = PartnerCompanyDocument::factory()->create([
        'file_path' => 'partner-companies/documents/rif.pdf',
    ]);

    $this->actingAs($this->admin)
        ->get(route('partner-companies.document', $document))
        ->assertOk();

    $this->actingAs(User::factory()->create(['roles' => []]))
        ->get(route('partner-companies.document', $document))
        ->assertForbidden();
});

test('deleting a partner document removes the stored file', function (): void {
    Storage::fake(PartnerCompanyDocument::DISK);
    Storage::disk(PartnerCompanyDocument::DISK)->put('partner-companies/documents/carta.pdf', 'pdf');

    $document = PartnerCompanyDocument::factory()->create([
        'file_path' => 'partner-companies/documents/carta.pdf',
    ]);

    $document->delete();

    Storage::disk(PartnerCompanyDocument::DISK)->assertMissing('partner-companies/documents/carta.pdf');
});
