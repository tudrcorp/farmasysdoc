<?php

namespace App\Services\Partners;

use App\Enums\InventoryMovementType;
use App\Enums\PartnerAgreementSaleStatus;
use App\Enums\PartnerFundMovementType;
use App\Models\ActiveIngredient;
use App\Models\Inventory;
use App\Models\InventoryLotBalance;
use App\Models\InventoryMovement;
use App\Models\PartnerAgreementSale;
use App\Models\PartnerAgreementSaleItem;
use App\Models\PartnerCompanyPlan;
use App\Models\PartnerPlanAffiliate;
use App\Models\Plan;
use App\Models\Product;
use App\Models\ProductLot;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Inventory\FefoLotSaleDispatchService;
use App\Support\Finance\DefaultVatRate;
use App\Support\Sales\ProductUnitPricingForBranch;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Venta por convenio (plan Flexible) hecha en caja:
 * - valida afiliado activo, plan Flexible vigente y aliado activo;
 * - si el plan tiene catálogos, solo vende productos cubiertos por sus principios activos;
 * - precio normal de la sucursal (con IVA si el producto lo grava), sin IGTF ni descuentos;
 * - bloquea el fondo y rechaza la venta si el saldo no alcanza;
 * - descuenta inventario y lotes FEFO, registra el consumo y numera la nota de entrega (NE-…).
 *
 * La anulación devuelve inventario, lotes y el monto al fondo.
 */
class PartnerAgreementSaleService
{
    public function __construct(
        private PartnerFundLedger $ledger,
        private FefoLotSaleDispatchService $fefo,
    ) {}

    /**
     * @param  list<array{product_id: int|string, quantity: float|int|string}>  $lines
     */
    public function register(
        int $branchId,
        int $affiliateId,
        array $lines,
        User $actor,
        ?float $vesPerUsd = null,
        ?string $notes = null,
    ): PartnerAgreementSale {
        $lines = $this->normalizeLines($lines);

        if ($lines === []) {
            throw ValidationException::withMessages(['line_items' => 'Cargue al menos un producto con cantidad mayor a cero.']);
        }

        $actorLabel = (string) ($actor->email ?: $actor->name);

        $sale = DB::transaction(function () use ($branchId, $affiliateId, $lines, $actor, $actorLabel, $vesPerUsd, $notes): PartnerAgreementSale {
            $affiliate = $this->resolveAffiliate($affiliateId);
            $assignment = $this->ledger->lock((int) $affiliate->partner_company_plan_id);
            $plan = $assignment->plan;

            $products = Product::query()->whereIn('id', array_keys($lines))->get()->keyBy('id');
            $this->assertProductsCovered($plan, $products->all());

            $pricedLines = $this->priceLines($branchId, $lines, $products->all());
            $subtotal = round(array_sum(array_column($pricedLines, 'line_subtotal')), 2);
            $taxTotal = round(array_sum(array_column($pricedLines, 'tax_amount')), 2);
            $total = round($subtotal + $taxTotal, 2);

            $available = $this->ledger->available($assignment);
            if ($total > $available + 0.001) {
                throw ValidationException::withMessages([
                    'fund' => 'Saldo insuficiente en el convenio: la venta es de $'.number_format($total, 2, ',', '.')
                        .' y el disponible es $'.number_format($available, 2, ',', '.').'.',
                ]);
            }

            $client = $affiliate->client;
            $rate = $vesPerUsd !== null && $vesPerUsd > 0 ? round($vesPerUsd, 4) : null;

            $sale = PartnerAgreementSale::query()->create([
                'branch_id' => $branchId,
                'partner_company_id' => $assignment->partner_company_id,
                'plan_id' => $assignment->plan_id,
                'partner_company_plan_id' => $assignment->getKey(),
                'partner_plan_affiliate_id' => $affiliate->getKey(),
                'client_id' => $client->getKey(),
                'client_name' => (string) $client->name,
                'client_document' => $client->document_number,
                'client_phone' => filled($client->phone) && $client->phone !== '—' ? $client->phone : null,
                'client_email' => self::deliverableEmail($client->email),
                'status' => PartnerAgreementSaleStatus::Completed,
                'subtotal' => $subtotal,
                'tax_total' => $taxTotal,
                'total' => $total,
                'bcv_ves_per_usd' => $rate,
                'total_ves' => $rate !== null ? round($total * $rate, 2) : null,
                'notes' => filled($notes) ? Str::limit(trim((string) $notes), 500, '') : null,
                'sold_at' => now(),
                'sold_by' => $actor->getKey(),
            ]);

            $sale->forceFill(['number' => PartnerAgreementSale::numberFor((int) $sale->getKey())])->save();

            foreach ($pricedLines as $line) {
                /** @var Product $product */
                $product = $line['product'];
                $inventory = $this->lockInventory($branchId, $product, $actorLabel);

                $inventory->quantity = round((float) $inventory->quantity - $line['quantity'], 3);
                $inventory->last_movement_at = now();
                $inventory->updated_by = $actorLabel;
                $inventory->save();

                PartnerAgreementSaleItem::query()->create([
                    'partner_agreement_sale_id' => $sale->getKey(),
                    'product_id' => $product->getKey(),
                    'inventory_id' => $inventory->getKey(),
                    'quantity' => $line['quantity'],
                    'unit_price' => $line['unit_price'],
                    'line_subtotal' => $line['line_subtotal'],
                    'tax_amount' => $line['tax_amount'],
                    'line_total' => $line['line_total'],
                    'unit_cost' => round(max(0.0, (float) ($product->cost_price ?? 0)), 2),
                    'product_name_snapshot' => (string) $product->name,
                    'sku_snapshot' => $product->sku,
                ]);

                $this->fefo->dispatchLine($branchId, $product, $line['quantity'], $inventory, $sale, 'Venta por convenio '.$sale->number, $actorLabel);
            }

            $movement = $this->ledger->record(
                $assignment,
                PartnerFundMovementType::Consumption,
                -$total,
                $actor,
                $sale,
                'Nota de entrega '.$sale->number.' · '.$client->name,
                $rate,
            );

            $sale->forceFill(['fund_balance_after_usd' => $movement->balance_after_usd])->save();

            return $sale;
        });

        AuditLogger::forModel($sale, 'partner_agreement_sale_registered', [
            'total_usd' => (float) $sale->total,
            'partner_company_id' => $sale->partner_company_id,
            'affiliate_id' => $sale->partner_plan_affiliate_id,
        ]);

        return $sale->fresh(['items', 'branch', 'partnerCompany', 'plan']);
    }

    /**
     * Anula la nota de entrega: devuelve inventario, lotes y el monto al fondo.
     */
    public function cancel(PartnerAgreementSale $sale, User $actor, string $reason): PartnerAgreementSale
    {
        if (blank($reason)) {
            throw ValidationException::withMessages(['reason' => 'Indique el motivo de la anulación.']);
        }

        $actorLabel = (string) ($actor->email ?: $actor->name);

        DB::transaction(function () use ($sale, $actor, $actorLabel, $reason): void {
            $locked = PartnerAgreementSale::query()->whereKey($sale->getKey())->lockForUpdate()->firstOrFail();

            if ($locked->isCancelled()) {
                throw ValidationException::withMessages(['status' => 'La nota de entrega ya está anulada.']);
            }

            $assignment = $this->ledger->lock((int) $locked->partner_company_plan_id);

            foreach ($locked->items as $item) {
                $this->restoreItem($locked, $item, $actorLabel);
            }

            $this->restoreLots($locked);

            $this->ledger->record(
                $assignment,
                PartnerFundMovementType::Reversal,
                (float) $locked->total,
                $actor,
                $locked,
                'Anulación '.$locked->number.': '.Str::limit(trim($reason), 200, ''),
                $locked->bcv_ves_per_usd !== null ? (float) $locked->bcv_ves_per_usd : null,
            );

            $locked->forceFill([
                'status' => PartnerAgreementSaleStatus::Cancelled,
                'cancelled_at' => now(),
                'cancelled_by' => $actor->getKey(),
                'cancellation_reason' => Str::limit(trim($reason), 500, ''),
            ])->save();
        });

        AuditLogger::forModel($sale, 'partner_agreement_sale_cancelled', ['reason' => $reason]);

        return $sale->fresh();
    }

    /**
     * Total que costaría la venta por convenio (sin guardar nada) y productos no cubiertos.
     *
     * @param  list<array{product_id: int|string, quantity: float|int|string}>  $lines
     * @return array{total: float, uncovered: list<string>}
     */
    public function quote(int $branchId, array $lines, ?Plan $plan = null): array
    {
        $normalized = $this->normalizeLines($lines);

        if ($normalized === [] || $branchId <= 0) {
            return ['total' => 0.0, 'uncovered' => []];
        }

        $products = Product::query()->whereIn('id', array_keys($normalized))->get()->keyBy('id')->all();

        if (count($products) !== count($normalized)) {
            return ['total' => 0.0, 'uncovered' => []];
        }

        $priced = $this->priceLines($branchId, $normalized, $products);

        return [
            'total' => round(array_sum(array_column($priced, 'line_total')), 2),
            'uncovered' => $plan instanceof Plan ? $this->uncoveredProducts($plan, $products) : [],
        ];
    }

    /**
     * Productos que el plan no cubre (vacío si el plan no tiene catálogos).
     *
     * @param  array<int, Product>  $products
     * @return list<string>
     */
    public function uncoveredProducts(Plan $plan, array $products): array
    {
        $formularyIds = $plan->formularies()->pluck('formularies.id')->all();

        if ($formularyIds === []) {
            return [];
        }

        $covered = ActiveIngredient::query()
            ->whereHas('formularyCategories.formularies', fn ($query) => $query->whereIn('formularies.id', $formularyIds))
            ->pluck('slug')
            ->filter()
            ->flip();

        $uncovered = [];

        foreach ($products as $product) {
            $ingredients = collect(is_array($product->active_ingredient) ? $product->active_ingredient : [])
                ->map(fn (mixed $name): string => Str::slug((string) $name))
                ->filter();

            if ($ingredients->isEmpty() || $ingredients->every(fn (string $slug): bool => ! $covered->has($slug))) {
                $uncovered[] = (string) $product->name;
            }
        }

        return $uncovered;
    }

    public static function deliverableEmail(?string $email): ?string
    {
        if (! filled($email) || str_ends_with((string) $email, '.invalid') || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return null;
        }

        return (string) $email;
    }

    private function resolveAffiliate(int $affiliateId): PartnerPlanAffiliate
    {
        $affiliate = PartnerPlanAffiliate::query()->with(['client', 'assignment.plan', 'assignment.partnerCompany'])->find($affiliateId);

        if (! $affiliate instanceof PartnerPlanAffiliate || ! $affiliate->is_active || $affiliate->client === null) {
            throw ValidationException::withMessages(['agreement_affiliate_id' => 'El cliente no está afiliado activo a este convenio.']);
        }

        $valid = PartnerAffiliateService::flexibleAssignmentsQuery()->whereKey($affiliate->partner_company_plan_id)->exists();

        if (! $valid) {
            throw ValidationException::withMessages(['agreement_affiliate_id' => 'El plan Flexible del convenio no está activo o el aliado está inactivo.']);
        }

        return $affiliate;
    }

    /**
     * @param  array<int, Product>  $products
     */
    private function assertProductsCovered(Plan $plan, array $products): void
    {
        $uncovered = $this->uncoveredProducts($plan, $products);

        if ($uncovered !== []) {
            throw ValidationException::withMessages([
                'line_items' => 'Estos productos no están cubiertos por los catálogos del plan: '.implode(', ', $uncovered).'.',
            ]);
        }
    }

    /**
     * @param  list<array{product_id: int|string, quantity: float|int|string}>  $lines
     * @return array<int, float> product_id => cantidad
     */
    private function normalizeLines(array $lines): array
    {
        $normalized = [];

        foreach ($lines as $line) {
            $productId = (int) ($line['product_id'] ?? 0);
            $quantity = round((float) ($line['quantity'] ?? 0), 3);

            if ($productId > 0 && $quantity > 0) {
                $normalized[$productId] = round(($normalized[$productId] ?? 0) + $quantity, 3);
            }
        }

        return $normalized;
    }

    /**
     * @param  array<int, float>  $lines
     * @param  array<int, Product>  $products
     * @return list<array{product: Product, quantity: float, unit_price: float, line_subtotal: float, tax_amount: float, line_total: float}>
     */
    private function priceLines(int $branchId, array $lines, array $products): array
    {
        $vatRate = DefaultVatRate::percent();
        $priced = [];

        foreach ($lines as $productId => $quantity) {
            $product = $products[$productId] ?? null;

            if (! $product instanceof Product) {
                throw ValidationException::withMessages(['line_items' => 'Uno de los productos ya no existe.']);
            }

            $inventory = Inventory::query()->where('branch_id', $branchId)->where('product_id', $productId)->first();
            $pricing = ProductUnitPricingForBranch::resolve($product, $branchId, $inventory);
            $unitNet = round((float) $pricing['unit_net'], 2);
            $lineSubtotal = round($unitNet * $quantity, 2);
            $tax = ($pricing['applies_vat'] ?? false) && $vatRate > 0 ? round($lineSubtotal * $vatRate / 100, 2) : 0.0;

            $priced[] = [
                'product' => $product,
                'quantity' => $quantity,
                'unit_price' => $unitNet,
                'line_subtotal' => $lineSubtotal,
                'tax_amount' => $tax,
                'line_total' => round($lineSubtotal + $tax, 2),
            ];
        }

        return $priced;
    }

    private function lockInventory(int $branchId, Product $product, string $actorLabel): Inventory
    {
        Inventory::query()->firstOrCreate(
            ['branch_id' => $branchId, 'product_id' => $product->getKey()],
            ['quantity' => 0, 'reserved_quantity' => 0, 'allow_negative_stock' => false, 'created_by' => $actorLabel, 'updated_by' => $actorLabel],
        );

        return Inventory::query()
            ->where('branch_id', $branchId)
            ->where('product_id', $product->getKey())
            ->lockForUpdate()
            ->firstOrFail();
    }

    private function restoreItem(PartnerAgreementSale $sale, PartnerAgreementSaleItem $item, string $actorLabel): void
    {
        $quantity = (float) $item->quantity;

        if ($quantity <= 0.00001) {
            return;
        }

        $inventory = Inventory::query()
            ->when($item->inventory_id, fn ($query) => $query->whereKey($item->inventory_id), fn ($query) => $query->where('branch_id', $sale->branch_id)->where('product_id', $item->product_id))
            ->lockForUpdate()
            ->first();

        if (! $inventory instanceof Inventory) {
            throw ValidationException::withMessages(['items' => 'No hay inventario para devolver «'.$item->product_name_snapshot.'».']);
        }

        $inventory->quantity = round((float) $inventory->quantity + $quantity, 3);
        $inventory->last_movement_at = now();
        $inventory->updated_by = $actorLabel;
        $inventory->save();

        InventoryMovement::query()->create([
            'product_id' => $item->product_id,
            'inventory_id' => $inventory->getKey(),
            'movement_type' => InventoryMovementType::Return,
            'quantity' => abs($quantity),
            'unit_cost' => (float) ($item->unit_cost ?? 0),
            'reference_type' => PartnerAgreementSale::class,
            'reference_id' => $sale->getKey(),
            'notes' => 'Anulación venta por convenio '.$sale->number,
            'created_by' => $actorLabel,
        ]);
    }

    /**
     * Devuelve a cada lote FEFO lo que la nota le descontó (los movimientos guardan factura y vencimiento).
     */
    private function restoreLots(PartnerAgreementSale $sale): void
    {
        $movements = InventoryMovement::query()
            ->where('reference_type', PartnerAgreementSale::class)
            ->where('reference_id', $sale->getKey())
            ->where('movement_type', InventoryMovementType::Sale)
            ->whereNotNull('expiry_date')
            ->get();

        foreach ($movements as $movement) {
            $quantity = round(abs((float) $movement->quantity), 3);
            $invoice = trim((string) ($movement->batch_number ?? ''));

            $lots = ProductLot::query()
                ->where('product_id', (int) $movement->product_id)
                ->where('expiration_month_year', $movement->expiry_date->format('m/Y'))
                ->when($invoice !== '', fn ($query) => $query->where('supplier_invoice_number', $invoice), fn ($query) => $query->where(fn ($q) => $q->whereNull('supplier_invoice_number')->orWhere('supplier_invoice_number', '')))
                ->orderByDesc('id')
                ->get();

            if ($lots->isEmpty() || $quantity <= 0.0001) {
                continue;
            }

            $withBalance = InventoryLotBalance::query()->where('branch_id', $sale->branch_id)->whereIn('product_lot_id', $lots->modelKeys())->value('product_lot_id');
            $lot = $withBalance !== null ? $lots->firstWhere('id', (int) $withBalance) : $lots->first();

            $balance = InventoryLotBalance::query()->where('branch_id', $sale->branch_id)->where('product_lot_id', $lot->getKey())->lockForUpdate()->first();

            if ($balance instanceof InventoryLotBalance) {
                $balance->forceFill(['quantity_remaining' => round((float) $balance->quantity_remaining + $quantity, 3)])->save();

                continue;
            }

            InventoryLotBalance::query()->create([
                'branch_id' => $sale->branch_id,
                'product_lot_id' => $lot->getKey(),
                'product_id' => (int) $movement->product_id,
                'quantity_remaining' => $quantity,
            ]);
        }
    }

    /**
     * Para la vista: asignación del afiliado con su disponible actual.
     */
    public function availableFor(PartnerPlanAffiliate $affiliate): float
    {
        $assignment = $affiliate->assignment;

        return $assignment instanceof PartnerCompanyPlan ? $assignment->fundAvailableUsd() : 0.0;
    }
}
