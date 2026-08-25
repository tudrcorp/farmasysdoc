<?php

use App\Enums\SaleStatus;
use App\Models\Branch;
use App\Models\BranchDailyOperation;
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
        ->and($report['total_usd'])->toBe(30.5)
        ->and($report['total_ves'])->toBe(1220.0);

    $terminalLine = collect($report['pos_terminals'])->firstWhere('id', $terminal->id);
    expect($terminalLine)->not->toBeNull()
        ->and((float) $terminalLine['amount_ves'])->toBe(200.0);
});
