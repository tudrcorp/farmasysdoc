<?php

use App\Models\PettyCashExpense;
use App\Models\User;
use App\Services\Finance\PettyCashExpenseCsvExporter;

test('el csv de egresos incluye filas y total', function (): void {
    $admin = User::factory()->create(['roles' => ['ADMINISTRADOR']]);
    PettyCashExpense::factory()->create([
        'description' => 'Papelería sucursal centro',
        'amount_usd' => 12.5,
        'amount_ves' => 456.25,
        'spent_at' => now(),
    ]);

    $exporter = app(PettyCashExpenseCsvExporter::class);
    $from = now()->startOfMonth();
    $to = now()->endOfMonth();
    $response = $exporter->stream($exporter->queryForUser($admin, $from, $to), 'egresos-test.csv');

    expect($response->headers->get('content-disposition'))->toContain('egresos-test.csv');

    ob_start();
    $response->sendContent();
    $content = (string) ob_get_clean();

    expect($content)->toContain('Papelería sucursal centro')
        ->and($content)->toContain('TOTAL')
        ->and($content)->toContain('12,50');
});
