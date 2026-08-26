<?php

use App\Enums\SaleStatus;
use App\Models\Branch;
use App\Models\BranchDailyOperation;
use App\Models\ConciliationCachea;
use App\Models\PosTerminal;
use App\Models\Sale;
use App\Services\Branches\BranchDailyOperationReconciliationBuilder;

it('consolida ventas de la sucursal por metodo de pago entre apertura y cierre', function (): void {
    $branch = Branch::factory()->create();
    $terminal = PosTerminal::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $operation = BranchDailyOperation::factory()->create([
        'branch_id' => $branch->id,
        'opened_at' => now()->subHours(3),
        'closed_at' => now(),
    ]);

    Sale::factory()->create([
        'branch_id' => $branch->id,
        'status' => SaleStatus::Completed,
        'payment_method' => 'pago_movil',
        'payment_usd' => 0,
        'payment_ves' => 400,
        'total' => 10,
        'bcv_ves_per_usd' => 40,
        'sold_at' => now()->subHours(2),
    ]);

    Sale::factory()->create([
        'branch_id' => $branch->id,
        'status' => SaleStatus::Completed,
        'payment_method' => 'punto_venta_ves',
        'pos_terminal_id' => $terminal->id,
        'payment_usd' => 0,
        'payment_ves' => 200,
        'total' => 5,
        'bcv_ves_per_usd' => 40,
        'sold_at' => now()->subHours(2),
    ]);

    Sale::factory()->create([
        'branch_id' => $branch->id,
        'status' => SaleStatus::Completed,
        'payment_method' => 'transfer_ves',
        'payment_usd' => 0,
        'payment_ves' => 80,
        'total' => 2,
        'bcv_ves_per_usd' => 40,
        'sold_at' => now()->subHours(2),
    ]);

    Sale::factory()->create([
        'branch_id' => $branch->id,
        'status' => SaleStatus::Completed,
        'payment_method' => 'efectivo_ves',
        'payment_usd' => 0,
        'payment_ves' => 40,
        'total' => 1,
        'bcv_ves_per_usd' => 40,
        'sold_at' => now()->subHours(2),
    ]);

    Sale::factory()->create([
        'branch_id' => $branch->id,
        'status' => SaleStatus::Completed,
        'payment_method' => 'efectivo_usd',
        'payment_usd' => 12.5,
        'payment_ves' => 0,
        'total' => 12.5,
        'bcv_ves_per_usd' => 40,
        'sold_at' => now()->subHours(2),
    ]);

    Sale::factory()->create([
        'branch_id' => $branch->id,
        'status' => SaleStatus::Completed,
        'payment_method' => 'efectivo_usd',
        'payment_usd' => 99,
        'payment_ves' => 0,
        'total' => 99,
        'bcv_ves_per_usd' => 40,
        'sold_at' => now()->subHours(5),
    ]);

    $report = app(BranchDailyOperationReconciliationBuilder::class)->build($branch, $operation);

    expect($report['sale_count'])->toBe(5)
        ->and($report['pago_movil_ves'])->toBe(400.0)
        ->and($report['punto_venta_ves'])->toBe(200.0)
        ->and($report['transfer_ves'])->toBe(80.0)
        ->and($report['efectivo_ves'])->toBe(40.0)
        ->and($report['efectivo_usd'])->toBe(12.5)
        ->and($report['total_usd'])->toBe(12.5)
        ->and($report['total_ves'])->toBe(720.0);

    $terminalLine = collect($report['pos_terminals'])->firstWhere('id', $terminal->id);
    expect($terminalLine)->not->toBeNull()
        ->and((float) $terminalLine['amount_ves'])->toBe(200.0);
});

it('no convierte bolivares a dolares ni incluye el financiamiento cashea', function (): void {
    $branch = Branch::factory()->create();
    $terminal = PosTerminal::factory()->create([
        'branch_id' => $branch->id,
    ]);

    $operation = BranchDailyOperation::factory()->create([
        'branch_id' => $branch->id,
        'opened_at' => now()->subHours(3),
        'closed_at' => now(),
    ]);

    $cacheaSale = Sale::factory()->create([
        'branch_id' => $branch->id,
        'status' => SaleStatus::Completed,
        'payment_method' => 'cachea',
        'payment_usd' => 29.67,
        'payment_ves' => 23293.01,
        'total' => 74.17,
        'bcv_ves_per_usd' => 785.0693,
        'pos_terminal_id' => $terminal->id,
        'reference' => 'CASHEA POS 002 ****0882',
        'sold_at' => now()->subHours(2),
    ]);

    ConciliationCachea::query()->create([
        'branch_id' => $branch->id,
        'sale_id' => $cacheaSale->id,
        'sale_number' => $cacheaSale->sale_number,
        'sale_total' => 74.17,
        'cachea_paid_amount' => 29.67,
        'remainder' => 44.50,
        'complement_payment_method' => 'punto_venta_ves',
        'reference' => 'CASHEA POS 002 ****0882',
        'recorded_at' => now()->subHours(2),
    ]);

    Sale::factory()->create([
        'branch_id' => $branch->id,
        'status' => SaleStatus::Completed,
        'payment_method' => 'zelle',
        'payment_usd' => 1104.99,
        'payment_ves' => 0,
        'total' => 1104.99,
        'bcv_ves_per_usd' => null,
        'sold_at' => now()->subHours(2),
    ]);

    Sale::factory()->create([
        'branch_id' => $branch->id,
        'status' => SaleStatus::Completed,
        'payment_method' => 'mixed',
        'payment_usd' => 2.00,
        'payment_ves' => 47.10,
        'total' => 2.06,
        'bcv_ves_per_usd' => 785.0693,
        'pos_terminal_id' => $terminal->id,
        'reference' => 'MIXTO POS 003 ****2855',
        'sold_at' => now()->subHours(2),
    ]);

    $report = app(BranchDailyOperationReconciliationBuilder::class)->build($branch, $operation);

    expect($report['sale_count'])->toBe(3)
        ->and($report['total_usd'])->toBe(1106.99)
        ->and($report['total_ves'])->toBe(23340.11)
        ->and($report['transfer_usd'])->toBe(1104.99)
        ->and($report['efectivo_usd'])->toBe(2.0)
        ->and($report['punto_venta_ves'])->toBe(23340.11)
        ->and($report['efectivo_ves'])->toBe(0.0);
});
