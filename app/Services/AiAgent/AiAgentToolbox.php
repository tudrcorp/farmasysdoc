<?php

namespace App\Services\AiAgent;

use App\Enums\SaleStatus;
use App\Models\AiAgentReport;
use App\Models\Sale;
use App\Models\User;
use App\Support\Sales\SaleCollectedMoney;
use App\Support\Sales\SaleCollectedMoneyAttributor;
use Illuminate\Database\Schema\Builder;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Throwable;

/**
 * Herramientas que Gemini puede pedir. Todas leen datos; ninguna modifica nada.
 */
class AiAgentToolbox
{
    /**
     * @var list<int>
     */
    private array $createdReportIds = [];

    public function __construct(
        private AiAgentReadOnlyDatabase $database,
        private AiAgentSqlGuard $guard,
        private SaleCollectedMoneyAttributor $attributor,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function declarations(): array
    {
        $period = [
            'from' => ['type' => 'STRING', 'description' => 'Fecha inicial YYYY-MM-DD'],
            'to' => ['type' => 'STRING', 'description' => 'Fecha final YYYY-MM-DD'],
            'branch_id' => ['type' => 'INTEGER', 'description' => 'Sucursal; omitir para todas', 'nullable' => true],
        ];

        return [
            $this->tool('sales_summary', 'Ventas completadas por día, mes o sucursal: número de ventas, total y ganancia bruta.', [
                ...$period,
                'group_by' => ['type' => 'STRING', 'enum' => ['day', 'month', 'branch']],
            ], ['from', 'to', 'group_by']),
            $this->tool('collected_money', 'Dinero realmente cobrado por moneda y método, igual que en las pantallas de ventas: efectivo en dólares, efectivo en bolívares, punto de venta, pago móvil y transferencias. Úsala para cualquier pregunta de cobros, caja o efectivo.', [
                ...$period,
            ], ['from', 'to']),
            $this->tool('top_products', 'Productos más o menos vendidos de un período.', [
                ...$period,
                'order' => ['type' => 'STRING', 'enum' => ['best', 'worst']],
                'limit' => ['type' => 'INTEGER'],
            ], ['from', 'to']),
            $this->tool('low_stock', 'Productos en o por debajo de su punto de reorden.', [
                'branch_id' => $period['branch_id'],
            ], []),
            $this->tool('expiring_lots', 'Lotes con existencia que vencen dentro de los próximos meses, o ya vencidos.', [
                'months' => ['type' => 'INTEGER', 'description' => 'Meses hacia adelante'],
                'branch_id' => $period['branch_id'],
            ], ['months']),
            $this->tool('finance_snapshot', 'Saldos pendientes de cuentas por pagar y por cobrar, por sucursal.', [], []),
            $this->tool('list_tables', 'Lista las tablas disponibles.', [], []),
            $this->tool('describe_table', 'Muestra las columnas disponibles de una tabla.', [
                'table' => ['type' => 'STRING'],
            ], ['table']),
            $this->tool('run_sql', 'Ejecuta un SELECT de MySQL para lo que no cubran las otras herramientas. Sin `*`: nombra las columnas.', [
                'sql' => ['type' => 'STRING'],
            ], ['sql']),
            $this->tool('create_report', 'Crea un reporte descargable en Excel y PDF a partir de un SELECT. Úsala cuando pidan un reporte.', [
                'title' => ['type' => 'STRING'],
                'sql' => ['type' => 'STRING'],
            ], ['title', 'sql']),
        ];
    }

    /**
     * Los errores vuelven a la IA para que corrija la consulta, no al usuario.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    public function execute(string $name, array $arguments, User $user): array
    {
        try {
            return match ($name) {
                'sales_summary' => $this->salesSummary($arguments),
                'collected_money' => $this->collectedMoney($arguments),
                'top_products' => $this->topProducts($arguments),
                'low_stock' => $this->lowStock($arguments),
                'expiring_lots' => $this->expiringLots($arguments),
                'finance_snapshot' => $this->financeSnapshot(),
                'list_tables' => $this->listTables(),
                'describe_table' => $this->describeTable((string) ($arguments['table'] ?? '')),
                'run_sql' => $this->runSql((string) ($arguments['sql'] ?? '')),
                'create_report' => $this->createReport((string) ($arguments['title'] ?? 'Reporte'), (string) ($arguments['sql'] ?? ''), $user),
                default => throw new RuntimeException("Herramienta desconocida: {$name}"),
            };
        } catch (Throwable $exception) {
            return ['error' => mb_substr($exception->getMessage(), 0, 500)];
        }
    }

    /**
     * @return list<int>
     */
    public function pullCreatedReportIds(): array
    {
        $ids = $this->createdReportIds;
        $this->createdReportIds = [];

        return $ids;
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function salesSummary(array $arguments): array
    {
        [$group, $label] = match ($arguments['group_by'] ?? 'month') {
            'day' => ['DATE(s.sold_at)', 'DATE(s.sold_at)'],
            'branch' => ['b.id', 'b.name'],
            default => ["DATE_FORMAT(s.sold_at, '%Y-%m')", "DATE_FORMAT(s.sold_at, '%Y-%m')"],
        };

        [$filter, $bindings] = $this->periodFilter($arguments);

        $rows = $this->database->selectTrusted(
            "SELECT {$label} AS period, COUNT(DISTINCT s.id) AS sales_count, ROUND(SUM(s.total), 2) AS total_usd
             FROM sales s JOIN branches b ON b.id = s.branch_id
             WHERE {$filter} GROUP BY {$group}, {$label} ORDER BY period",
            $bindings,
        );

        $profit = $this->database->selectTrusted(
            "SELECT ROUND(SUM(i.gross_profit), 2) AS gross_profit_usd FROM sale_items i JOIN sales s ON s.id = i.sale_id WHERE {$filter}",
            $bindings,
        );

        return ['rows' => $rows, 'gross_profit_usd' => $profit[0]['gross_profit_usd'] ?? null];
    }

    /**
     * Usa la misma atribución de cobros de las pantallas de ventas, para que las cifras coincidan.
     *
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function collectedMoney(array $arguments): array
    {
        $byBranch = [];

        Sale::query()
            ->with('branch:id,name')
            ->where('status', SaleStatus::Completed)
            ->whereDate('sold_at', '>=', (string) ($arguments['from'] ?? ''))
            ->whereDate('sold_at', '<=', (string) ($arguments['to'] ?? ''))
            ->when(filled($arguments['branch_id'] ?? null), fn ($query) => $query->where('branch_id', $arguments['branch_id']))
            ->chunkById(500, function ($sales) use (&$byBranch): void {
                foreach ($sales as $sale) {
                    $name = (string) ($sale->branch?->name ?? 'Sin sucursal');
                    $byBranch[$name] = ($byBranch[$name] ?? SaleCollectedMoney::empty())->add($this->attributor->attribute($sale));
                }
            });

        $rows = [];
        $total = SaleCollectedMoney::empty();

        foreach ($byBranch as $branch => $money) {
            $rows[] = ['branch' => $branch, ...$this->moneyColumns($money)];
            $total = $total->add($money);
        }

        return ['rows' => $rows, 'total' => $this->moneyColumns($total)];
    }

    /**
     * @return array<string, float>
     */
    private function moneyColumns(SaleCollectedMoney $money): array
    {
        return [
            'efectivo_usd' => $money->efectivoUsd,
            'transferencia_o_zelle_usd' => $money->transferUsd,
            'efectivo_ves' => $money->efectivoVes,
            'punto_de_venta_ves' => $money->posVes,
            'pago_movil_ves' => $money->pagoMovilVes,
            'transferencia_ves' => $money->transferVes,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function topProducts(array $arguments): array
    {
        [$filter, $bindings] = $this->periodFilter($arguments);
        $direction = ($arguments['order'] ?? 'best') === 'worst' ? 'ASC' : 'DESC';
        $limit = min(100, max(1, (int) ($arguments['limit'] ?? 20)));

        return ['rows' => $this->database->selectTrusted(
            "SELECT i.product_name_snapshot AS product, ROUND(SUM(i.quantity), 2) AS units,
                    ROUND(SUM(i.line_total), 2) AS total_usd, ROUND(SUM(i.gross_profit), 2) AS gross_profit_usd
             FROM sale_items i JOIN sales s ON s.id = i.sale_id
             WHERE {$filter} GROUP BY i.product_id, i.product_name_snapshot
             ORDER BY units {$direction} LIMIT {$limit}",
            $bindings,
        )];
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function lowStock(array $arguments): array
    {
        $branchId = $arguments['branch_id'] ?? null;

        return $this->limited($this->database->selectTrusted(
            'SELECT p.name AS product, b.name AS branch, inv.quantity, inv.reorder_point
             FROM inventories inv JOIN products p ON p.id = inv.product_id JOIN branches b ON b.id = inv.branch_id
             WHERE inv.reorder_point IS NOT NULL AND inv.quantity <= inv.reorder_point
               AND (? IS NULL OR inv.branch_id = ?)
             ORDER BY inv.quantity LIMIT 200',
            [$branchId, $branchId],
        ));
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array<string, mixed>
     */
    private function expiringLots(array $arguments): array
    {
        $branchId = $arguments['branch_id'] ?? null;
        $limitMonth = now()->addMonths(max(0, (int) ($arguments['months'] ?? 3)))->format('Ym');

        return $this->limited($this->database->selectTrusted(
            "SELECT p.name AS product, b.name AS branch, l.expiration_month_year, ROUND(SUM(bal.quantity_remaining), 2) AS units
             FROM inventory_lot_balances bal
             JOIN product_lots l ON l.id = bal.product_lot_id
             JOIN products p ON p.id = bal.product_id
             JOIN branches b ON b.id = bal.branch_id
             WHERE bal.quantity_remaining > 0
               AND CONCAT(SUBSTRING_INDEX(l.expiration_month_year, '/', -1), LPAD(SUBSTRING_INDEX(l.expiration_month_year, '/', 1), 2, '0')) <= ?
               AND (? IS NULL OR bal.branch_id = ?)
             GROUP BY p.name, b.name, l.expiration_month_year
             ORDER BY CONCAT(SUBSTRING_INDEX(l.expiration_month_year, '/', -1), LPAD(SUBSTRING_INDEX(l.expiration_month_year, '/', 1), 2, '0'))
             LIMIT 200",
            [$limitMonth, $branchId, $branchId],
        ));
    }

    /**
     * @return array<string, mixed>
     */
    private function financeSnapshot(): array
    {
        $balances = fn (string $table): array => $this->database->selectTrusted(
            "SELECT b.name AS branch, COUNT(*) AS documents, ROUND(SUM(t.remaining_principal_usd), 2) AS pending_usd
             FROM {$table} t JOIN branches b ON b.id = t.branch_id
             WHERE t.remaining_principal_usd > 0 GROUP BY b.name",
        );

        return ['payable' => $balances('accounts_payables'), 'receivable' => $balances('accounts_receivables')];
    }

    /**
     * @return array<string, mixed>
     */
    private function listTables(): array
    {
        $tables = array_column($this->schema()->getTables($this->database->connection()->getDatabaseName()), 'name');

        return ['tables' => array_values(array_filter($tables, fn (string $table): bool => $this->guard->isTableAllowed($table)))];
    }

    /**
     * @return array<string, mixed>
     */
    private function describeTable(string $table): array
    {
        if (! $this->guard->isTableAllowed($table)) {
            throw new RuntimeException('Esta tabla no está disponible.');
        }

        $columns = $this->schema()->getColumns($table);

        return ['columns' => array_values(array_map(
            fn (array $column): array => ['name' => $column['name'], 'type' => $column['type']],
            array_filter($columns, fn (array $column): bool => $this->guard->isColumnAllowed($column['name'])),
        ))];
    }

    /**
     * @return array<string, mixed>
     */
    private function runSql(string $sql): array
    {
        $maxRows = (int) config('ai_agent.max_rows_for_model');
        $rows = $this->database->select($sql, $maxRows + 1);
        $truncated = count($rows) > $maxRows;

        return [
            'rows' => array_slice($rows, 0, $maxRows),
            'truncated' => $truncated,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function createReport(string $title, string $sql, User $user): array
    {
        $maxRows = (int) config('ai_agent.max_rows_for_report');
        $rows = $this->database->select($sql, $maxRows);

        if ($rows === []) {
            return ['error' => 'La consulta no devolvió filas.'];
        }

        $report = AiAgentReport::query()->create([
            'user_id' => $user->id,
            'title' => mb_substr($title, 0, 160),
            'columns' => array_keys($rows[0]),
            'rows' => array_map('array_values', $rows),
        ]);

        $this->createdReportIds[] = $report->id;

        return [
            'created' => true,
            'row_count' => count($rows),
            'truncated' => count($rows) === $maxRows,
            'preview' => array_slice($rows, 0, 10),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return array<string, mixed>
     */
    private function limited(array $rows): array
    {
        return ['rows' => $rows, 'truncated' => count($rows) >= 200];
    }

    private function schema(): Builder
    {
        return Schema::connection(AiAgentReadOnlyDatabase::CONNECTION);
    }

    /**
     * @param  array<string, mixed>  $arguments
     * @return array{0: string, 1: list<mixed>}
     */
    private function periodFilter(array $arguments): array
    {
        $branchId = $arguments['branch_id'] ?? null;

        return [
            's.status = ? AND DATE(s.sold_at) BETWEEN ? AND ? AND (? IS NULL OR s.branch_id = ?)',
            [SaleStatus::Completed->value, $arguments['from'] ?? null, $arguments['to'] ?? null, $branchId, $branchId],
        ];
    }

    /**
     * @param  array<string, mixed>  $properties
     * @param  list<string>  $required
     * @return array<string, mixed>
     */
    private function tool(string $name, string $description, array $properties, array $required): array
    {
        $declaration = ['name' => $name, 'description' => $description];

        if ($properties !== []) {
            $declaration['parameters'] = ['type' => 'OBJECT', 'properties' => $properties, 'required' => $required];
        }

        return $declaration;
    }
}
