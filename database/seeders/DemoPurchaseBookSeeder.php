<?php

namespace Database\Seeders;

use App\Enums\PurchaseEntryCurrency;
use App\Enums\PurchaseStatus;
use App\Models\Branch;
use App\Models\Purchase;
use App\Models\Supplier;
use App\Services\Finance\PurchaseBookFromPurchaseSynchronizer;
use App\Support\Finance\DefaultVatRate;
use App\Support\Purchases\PurchasePaymentStatus;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Carga ~30 filas de Retenciones con proveedores repetidos (datos de prueba).
 */
class DemoPurchaseBookSeeder extends Seeder
{
    private const int TARGET_BOOKS = 30;

    public function run(): void
    {
        $branch = Branch::query()->where('is_active', true)->first()
            ?? Branch::query()->first();

        if ($branch === null) {
            $this->command?->error('No hay sucursales. Ejecute BranchSeeder primero.');

            return;
        }

        $suppliers = $this->ensureDemoSuppliers();
        $vatRate = DefaultVatRate::percent();
        $createdBooks = 0;
        $skipped = 0;

        $batchToken = now()->format('ymdHis');

        DB::transaction(function () use ($branch, $suppliers, $vatRate, $batchToken, &$createdBooks, &$skipped): void {
            $sync = app(PurchaseBookFromPurchaseSynchronizer::class);

            for ($i = 1; $i <= self::TARGET_BOOKS; $i++) {
                /** @var Supplier $supplier */
                $supplier = $suppliers[($i - 1) % $suppliers->count()];

                // Repartir fechas en dos periodos para validar correlativos por yyyy/mm.
                $invoiceDate = $i <= 18
                    ? Carbon::create(2026, 7, (($i - 1) % 28) + 1)
                    : Carbon::create(2026, 6, (($i - 1) % 28) + 1);

                $subtotal = round(150 + ($i * 37.25), 2);
                $taxTotal = round($subtotal * ($vatRate / 100), 2);
                $total = round($subtotal + $taxTotal, 2);

                $purchase = Purchase::query()->create([
                    'purchase_number' => sprintf('OC-DEMO-LB-%s-%04d', $batchToken, $i),
                    'supplier_id' => $supplier->id,
                    'branch_id' => $branch->id,
                    'entry_currency' => PurchaseEntryCurrency::USD,
                    'official_usd_ves_rate' => null,
                    'status' => PurchaseStatus::Received,
                    'ordered_at' => $invoiceDate->copy()->subDays(2),
                    'expected_delivery_at' => $invoiceDate->copy()->addDays(5),
                    'received_at' => $invoiceDate,
                    'subtotal' => $subtotal,
                    'subtotal_exempt_amount' => 0,
                    'subtotal_taxable_amount' => $subtotal,
                    'tax_total' => $taxTotal,
                    'discount_total' => 0,
                    'document_discount_percent' => 0,
                    'document_discount_amount' => 0,
                    'net_exempt_after_document_discount' => 0,
                    'net_taxable_after_document_discount' => $subtotal,
                    'total' => $total,
                    'declared_invoice_total' => $total,
                    'supplier_invoice_number' => sprintf('FAC-DEMO-%s-%s-%03d', $batchToken, $supplier->getKey(), $i),
                    'supplier_control_number' => sprintf('CTRL-DEMO-%s-%05d', $batchToken, 10000 + $i),
                    'supplier_invoice_date' => $invoiceDate->toDateString(),
                    'payment_due_date' => $invoiceDate->copy()->addDays(30)->toDateString(),
                    'registered_in_system_date' => $invoiceDate->toDateString(),
                    'payment_status' => PurchasePaymentStatus::PAGADO_CONTADO,
                    'notes' => 'Compra demo Retenciones #'.$i,
                    'created_by' => 'demo-seeder',
                    'updated_by' => 'demo-seeder',
                ]);

                $book = $sync->syncFromPurchase($purchase->fresh(['supplier']));
                if ($book !== null) {
                    $createdBooks++;
                } else {
                    $skipped++;
                }
            }
        });

        $sameDayCreated = $this->seedSameDayDuplicatePurchases($branch, $suppliers, $vatRate);

        $this->command?->info("Retenciones demo: {$createdBooks} registros creados, {$skipped} omitidos.");
        $this->command?->info("Mismo proveedor / misma fecha: {$sameDayCreated} registros adicionales (pares).");
        $this->command?->info('Proveedores usados (repetidos): '.$suppliers->pluck('legal_name')->implode(' | '));
    }

    /**
     * Crea 2 compras el mismo día por proveedor (varios proveedores) para validar agrupaciones.
     *
     * @param  Collection<int, Supplier>  $suppliers
     */
    public function seedSameDayDuplicatePurchases(
        ?Branch $branch = null,
        ?Collection $suppliers = null,
        ?float $vatRate = null,
    ): int {
        $branch ??= Branch::query()->where('is_active', true)->first()
            ?? Branch::query()->first();

        if ($branch === null) {
            $this->command?->error('No hay sucursales.');

            return 0;
        }

        $suppliers ??= $this->ensureDemoSuppliers();
        $vatRate ??= DefaultVatRate::percent();
        $batchToken = 'SD'.now()->format('ymdHis');
        $created = 0;

        // Tres proveedores × 2 facturas el 2026-07-10; otros dos × 2 el 2026-07-15.
        $scenarios = [
            ['date' => Carbon::create(2026, 7, 10), 'supplier_indexes' => [0, 1, 2]],
            ['date' => Carbon::create(2026, 7, 15), 'supplier_indexes' => [3, 4]],
        ];

        $sync = app(PurchaseBookFromPurchaseSynchronizer::class);

        foreach ($scenarios as $scenario) {
            /** @var Carbon $sameDay */
            $sameDay = $scenario['date'];

            foreach ($scenario['supplier_indexes'] as $supplierIndex) {
                $supplier = $suppliers[$supplierIndex] ?? null;
                if (! $supplier instanceof Supplier) {
                    continue;
                }

                for ($n = 1; $n <= 2; $n++) {
                    $subtotal = round(500 + (($supplierIndex + 1) * 100) + ($n * 25.5), 2);
                    $taxTotal = round($subtotal * ($vatRate / 100), 2);
                    $total = round($subtotal + $taxTotal, 2);

                    $purchase = Purchase::query()->create([
                        'purchase_number' => sprintf('OC-SAME-DAY-%s-%d-%d', $batchToken, $supplier->getKey(), $n),
                        'supplier_id' => $supplier->id,
                        'branch_id' => $branch->id,
                        'entry_currency' => PurchaseEntryCurrency::USD,
                        'official_usd_ves_rate' => null,
                        'status' => PurchaseStatus::Received,
                        'ordered_at' => $sameDay->copy()->subDay(),
                        'expected_delivery_at' => $sameDay->copy()->addDays(3),
                        'received_at' => $sameDay,
                        'subtotal' => $subtotal,
                        'subtotal_exempt_amount' => 0,
                        'subtotal_taxable_amount' => $subtotal,
                        'tax_total' => $taxTotal,
                        'discount_total' => 0,
                        'document_discount_percent' => 0,
                        'document_discount_amount' => 0,
                        'net_exempt_after_document_discount' => 0,
                        'net_taxable_after_document_discount' => $subtotal,
                        'total' => $total,
                        'declared_invoice_total' => $total,
                        'supplier_invoice_number' => sprintf('FAC-SAME-%s-%d-%d', $batchToken, $supplier->getKey(), $n),
                        'supplier_control_number' => sprintf('CTRL-SAME-%s-%d-%d', $batchToken, $supplier->getKey(), $n),
                        'supplier_invoice_date' => $sameDay->toDateString(),
                        'payment_due_date' => $sameDay->copy()->addDays(30)->toDateString(),
                        'registered_in_system_date' => $sameDay->toDateString(),
                        'payment_status' => PurchasePaymentStatus::PAGADO_CONTADO,
                        'notes' => 'Demo mismo día: '.$supplier->legal_name.' factura #'.$n,
                        'created_by' => 'demo-seeder',
                        'updated_by' => 'demo-seeder',
                    ]);

                    $book = $sync->syncFromPurchase($purchase->fresh(['supplier']));
                    if ($book !== null) {
                        $created++;
                    }
                }
            }
        }

        return $created;
    }

    /**
     * @return Collection<int, Supplier>
     */
    private function ensureDemoSuppliers(): Collection
    {
        $defs = [
            [
                'tax_id' => 'J401112223',
                'legal_name' => 'DISTRIBUIDORA FARMA ANDES, C.A.',
                'trade_name' => 'Farma Andes',
                'address' => 'Av. Principal Los Próceres, Edif. Andes, Piso 2, Caracas',
                'seniat_retention_percent' => 75,
            ],
            [
                'tax_id' => 'J309998887',
                'legal_name' => 'LABORATORIOS DEL SUR, C.A.',
                'trade_name' => 'LabSur',
                'address' => 'Calle Comercio Nro 45, Valencia, Carabobo',
                'seniat_retention_percent' => 100,
            ],
            [
                'tax_id' => 'J123456789',
                'legal_name' => 'IMPORTADORA MEDICA ORIENTE, C.A.',
                'trade_name' => 'MedOriente',
                'address' => 'Av. Bolívar Sector Centro, Barcelona, Anzoátegui',
                'seniat_retention_percent' => 75,
            ],
            [
                'tax_id' => 'J987654321',
                'legal_name' => 'DROGUERIA CENTRAL BARINAS, C.A.',
                'trade_name' => 'Droguería Central',
                'address' => 'Carrera 5 entre calles 8 y 9, Barinas',
                'seniat_retention_percent' => 0,
            ],
            [
                'tax_id' => 'J556677889',
                'legal_name' => 'SUMINISTROS HOSPITALARIOS ZULIA, C.A.',
                'trade_name' => 'SumiZulia',
                'address' => 'Av. 5 de Julio, Maracaibo, Zulia',
                'seniat_retention_percent' => 100,
            ],
            [
                'tax_id' => 'J778899001',
                'legal_name' => 'COMERCIALIZADORA ALPHA PHARMA, C.A.',
                'trade_name' => 'Alpha Pharma',
                'address' => 'Urb. Industrial Los Cortijos, Caracas',
                'seniat_retention_percent' => 75,
            ],
        ];

        return collect($defs)->map(function (array $def, int $index): Supplier {
            $supplier = Supplier::query()->firstOrNew(['tax_id' => $def['tax_id']]);
            $isNew = ! $supplier->exists;

            $supplier->fill([
                'legal_name' => $def['legal_name'],
                'trade_name' => $def['trade_name'],
                'tax_id' => $def['tax_id'],
                'seniat_retention_percent' => $def['seniat_retention_percent'],
                'address' => $def['address'],
                'city' => 'Caracas',
                'state' => 'Distrito Capital',
                'country' => 'Venezuela',
                'email' => 'demo.libro'.($index + 1).'@example.test',
                'phone' => '0212123456'.($index + 1),
                'mobile_phone' => '0414123456'.($index + 1),
                'is_active' => true,
                'updated_by' => 'demo-seeder',
            ]);

            if ($isNew) {
                $supplier->created_by = 'demo-seeder';
                $supplier->code = null;
            }

            $supplier->save();

            if ($isNew || blank($supplier->code)) {
                $supplier->forceFill([
                    'code' => Supplier::formatCode($supplier->getKey()),
                ])->save();
            }

            return $supplier->fresh();
        })->values();
    }
}
