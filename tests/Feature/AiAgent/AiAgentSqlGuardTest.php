<?php

use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Models\User;
use App\Services\AiAgent\AiAgentSqlGuard;
use App\Services\AiAgent\AiAgentToolbox;

it('accepts read queries', function (string $sql): void {
    expect(app(AiAgentSqlGuard::class)->validate($sql))->toBeString();
})->with([
    'SELECT name, quantity FROM inventories;',
    'WITH t AS (SELECT branch_id, SUM(total) AS total FROM sales GROUP BY branch_id) SELECT branch_id, total FROM t',
    "SELECT COUNT(*) AS total FROM sales WHERE notes LIKE '%email%'",
]);

it('rejects unsafe queries', function (string $sql): void {
    app(AiAgentSqlGuard::class)->validate($sql);
})->with([
    'escritura' => 'DELETE FROM sales',
    'dos consultas' => 'SELECT id FROM sales; DROP TABLE sales',
    'asterisco' => 'SELECT * FROM clients',
    'asterisco con tabla' => 'SELECT c.* FROM clients c',
    'contraseña' => 'SELECT password FROM users',
    'cédula con alias' => 'SELECT national_id AS codigo FROM employees',
    'teléfono' => 'SELECT phone FROM clients',
    'tabla bloqueada' => 'SELECT id FROM personal_access_tokens',
    'catálogo' => 'SELECT table_name FROM information_schema.tables',
    'comentario' => 'SELECT id FROM sales -- x',
    'archivo' => "SELECT id INTO OUTFILE '/tmp/x' FROM sales",
    'espera' => 'SELECT SLEEP(100)',
])->throws(RuntimeException::class);

it('reports collected cash by currency like the sales screens', function (): void {
    $user = User::factory()->create();
    $sale = Sale::factory()->create([
        'status' => SaleStatus::Completed,
        'sold_at' => '2026-09-10 10:00:00',
        'payment_method' => 'efectivo_usd',
        'payment_usd' => 25,
        'payment_ves' => 0,
    ]);

    $result = app(AiAgentToolbox::class)->execute('collected_money', ['from' => '2026-09-01', 'to' => '2026-09-30'], $user);

    expect($result['total']['efectivo_usd'])->toBe(25.0)
        ->and($result['rows'][0]['branch'])->toBe($sale->branch->name);
});
