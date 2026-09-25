<?php

namespace App\Support\Reports;

use App\Enums\SaleStatus;
use App\Models\AccountsPayable;
use App\Models\AccountsReceivable;
use App\Models\Inventory;
use App\Models\InventoryLotBalance;
use App\Models\PhysicalCashBox;
use App\Models\PurchaseBook;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\Finance\AccountsReceivableStatus;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

final class ExecutiveKpiBoard
{
    /**
     * @return array{
     *     period_label: string,
     *     cards: list<array{
     *         number: int,
     *         title: string,
     *         summary: string,
     *         metrics: list<array{label: string, value: string}>,
     *         note: string
     *     }>,
     *     ceo_note: string
     * }
     */
    public function snapshot(?CarbonInterface $now = null): array
    {
        $now = $now !== null ? Carbon::parse($now) : now();
        $monthStart = $now->copy()->startOfMonth();
        $monthEnd = $now->copy()->endOfMonth();

        return [
            'period_label' => $now->locale('es')->isoFormat('MMMM YYYY'),
            'cards' => [
                $this->cashOnHand(),
                $this->netCashFlow($monthStart, $monthEnd),
                $this->receivables($now),
                $this->payables($now),
                $this->taxes($monthStart, $monthEnd),
                $this->inventoryValue(),
                $this->inventoryTurn($monthStart, $monthEnd),
                $this->expiries($now),
                $this->margin($monthStart, $monthEnd),
            ],
            'ceo_note' => 'Los pagos al SENIAT por retenciones reducen una obligación. No se suman otra vez como gasto operativo.',
        ];
    }

    /**
     * @return array{number: int, title: string, summary: string, metrics: list<array{label: string, value: string}>, note: string}
     */
    private function cashOnHand(): array
    {
        $usd = round((float) PhysicalCashBox::query()->sum('amount_usd'), 2);
        $ves = round((float) PhysicalCashBox::query()->sum('amount_ves'), 2);
        $open = PhysicalCashBox::query()->where('is_open', true)->count();

        return $this->card(
            1,
            'Efectivo disponible',
            'Saldo de las cajas físicas, en dólares y en bolívares, sin convertir una moneda en la otra.',
            [
                ['label' => 'Cajas en USD', 'value' => $this->usd($usd)],
                ['label' => 'Cajas en Bs', 'value' => $this->ves($ves)],
                ['label' => 'Cajas abiertas', 'value' => (string) $open],
            ],
            'Es el efectivo que tienen los cajeros. El saldo bancario conciliado vive en la conciliación, no en esta caja.',
        );
    }

    /**
     * @return array{number: int, title: string, summary: string, metrics: list<array{label: string, value: string}>, note: string}
     */
    private function netCashFlow(CarbonInterface $monthStart, CarbonInterface $monthEnd): array
    {
        $collected = round((float) Sale::query()
            ->where('status', SaleStatus::Completed->value)
            ->whereBetween('sold_at', [$monthStart, $monthEnd])
            ->sum('payment_usd'), 2);

        $paidPurchases = round((float) AccountsPayable::query()
            ->whereBetween('paid_at', [$monthStart, $monthEnd])
            ->sum('purchase_total_usd'), 2);
        $net = round($collected - $paidPurchases, 2);

        return $this->card(
            2,
            'Flujo neto de caja',
            'Cobros de ventas completadas menos compras marcadas como pagadas en el mes.',
            [
                ['label' => 'Cobros del mes', 'value' => $this->usd($collected)],
                ['label' => 'Compras pagadas', 'value' => $this->usd($paidPurchases)],
                ['label' => 'Neto del mes', 'value' => $this->usd($net), 'tone' => $net < 0 ? 'down' : 'up'],
            ],
            'Operación del mes. No incluye inversión ni financiamiento fuera de ventas y cuentas por pagar.',
        );
    }

    /**
     * @return array{number: int, title: string, summary: string, metrics: list<array{label: string, value: string}>, note: string}
     */
    private function receivables(CarbonInterface $now): array
    {
        $open = AccountsReceivable::query()->where('status', AccountsReceivableStatus::POR_COBRAR);
        $balance = round((float) (clone $open)->sum('remaining_principal_usd'), 2);
        $count = (clone $open)->count();
        $overdue = (clone $open)->whereNotNull('due_at')->where('due_at', '<', $now->copy()->startOfDay())->count();
        $top = AccountsReceivable::query()
            ->where('status', AccountsReceivableStatus::POR_COBRAR)
            ->selectRaw('client_name_snapshot, SUM(remaining_principal_usd) as balance')
            ->groupBy('client_name_snapshot')
            ->orderByDesc('balance')
            ->first();

        $topLabel = $top !== null && filled($top->client_name_snapshot)
            ? (string) $top->client_name_snapshot
            : 'Sin cartera abierta';

        return $this->card(
            3,
            'Cuentas por cobrar',
            'Saldo pendiente de facturas a crédito y cuántas ya vencieron.',
            [
                ['label' => 'Saldo por cobrar', 'value' => $this->usd($balance)],
                ['label' => 'Facturas abiertas', 'value' => (string) $count],
                ['label' => 'Vencidas', 'value' => (string) $overdue],
                ['label' => 'Mayor saldo', 'value' => $topLabel, 'tone' => 'text'],
            ],
            'El cliente con mayor saldo aparece para revisar convenios corporativos.',
        );
    }

    /**
     * @return array{number: int, title: string, summary: string, metrics: list<array{label: string, value: string}>, note: string}
     */
    private function payables(CarbonInterface $now): array
    {
        $open = AccountsPayable::query()->where('status', 'por_pagar');
        $usd = round((float) (clone $open)->sum('remaining_principal_usd'), 2);
        $ves = round((float) (clone $open)->sum('current_balance_ves'), 2);
        $overdue = (clone $open)->whereNotNull('due_at')->where('due_at', '<', $now->copy()->startOfDay())->count();

        return $this->card(
            4,
            'Cuentas por pagar',
            'Lo que sigue por pagar a proveedores, en dólares de la factura y en bolívares al día.',
            [
                ['label' => 'Principal USD', 'value' => $this->usd($usd)],
                ['label' => 'Saldo al día Bs', 'value' => $this->ves($ves)],
                ['label' => 'Vencidas', 'value' => (string) $overdue],
            ],
            'Nómina y otros pagos que no están en cuentas por pagar no entran en este saldo.',
        );
    }

    /**
     * @return array{number: int, title: string, summary: string, metrics: list<array{label: string, value: string}>, note: string}
     */
    private function taxes(CarbonInterface $monthStart, CarbonInterface $monthEnd): array
    {
        $retained = round((float) PurchaseBook::query()
            ->whereBetween('invoice_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->sum('tax_retained_ves'), 2);
        $vouchers = PurchaseBook::query()
            ->whereBetween('invoice_date', [$monthStart->toDateString(), $monthEnd->toDateString()])
            ->where('tax_retained_ves', '>', 0)
            ->count();

        return $this->card(
            5,
            'Impuestos y retenciones',
            'IVA retenido en el libro de compras del mes. Es una obligación con el SENIAT, no un gasto de la operación.',
            [
                ['label' => 'IVA retenido del mes', 'value' => $this->ves($retained)],
                ['label' => 'Comprobantes con retención', 'value' => (string) $vouchers],
            ],
            'Al enterarlo se reduce la deuda. No se vuelve a contar como gasto.',
        );
    }

    /**
     * @return array{number: int, title: string, summary: string, metrics: list<array{label: string, value: string}>, note: string}
     */
    private function inventoryValue(): array
    {
        $value = round((float) Inventory::query()
            ->selectRaw('COALESCE(SUM(quantity * cost_price), 0) as value')
            ->value('value'), 2);
        $units = round((float) Inventory::query()->sum('quantity'), 0);

        $topCategory = Inventory::query()
            ->join('products', 'products.id', '=', 'inventories.product_id')
            ->leftJoin('product_categories', 'product_categories.id', '=', 'products.product_category_id')
            ->selectRaw("COALESCE(product_categories.name, 'Sin categoría') as category_name, COALESCE(SUM(inventories.quantity * inventories.cost_price), 0) as value")
            ->groupBy('category_name')
            ->orderByDesc('value')
            ->first();

        return $this->card(
            6,
            'Valor del inventario',
            'Existencias valorizadas al costo de cada fila de inventario, por sucursal sumadas.',
            [
                ['label' => 'Valor al costo', 'value' => $this->usd($value)],
                ['label' => 'Unidades', 'value' => number_format($units, 0, ',', '.')],
                ['label' => 'Categoría de mayor valor', 'value' => (string) ($topCategory->category_name ?? '—')],
            ],
            'Usa el costo guardado en inventario, no el precio de venta.',
        );
    }

    /**
     * @return array{number: int, title: string, summary: string, metrics: list<array{label: string, value: string}>, note: string}
     */
    private function inventoryTurn(CarbonInterface $monthStart, CarbonInterface $monthEnd): array
    {
        $stock = round((float) Inventory::query()
            ->selectRaw('COALESCE(SUM(quantity * cost_price), 0) as value')
            ->value('value'), 2);
        $cogs = round((float) SaleItem::query()
            ->whereHas('sale', function ($query) use ($monthStart, $monthEnd): void {
                $query->where('status', SaleStatus::Completed->value)
                    ->whereBetween('sold_at', [$monthStart, $monthEnd]);
            })
            ->sum('line_cost_total'), 2);
        $daysInMonth = max(1, $monthStart->daysInMonth);
        $dailyCogs = $cogs / $daysInMonth;
        $days = $dailyCogs > 0.00001 ? round($stock / $dailyCogs, 0) : null;

        return $this->card(
            7,
            'Rotación / días de inventario',
            'Cuántos días de venta al costo cubre el inventario actual, con el ritmo de este mes.',
            [
                ['label' => 'Costo vendido en el mes', 'value' => $this->usd($cogs)],
                ['label' => 'Días de inventario', 'value' => $days === null ? 'Sin ventas' : number_format($days, 0, ',', '.').' días'],
            ],
            'Menos días significa que el stock se vende más rápido.',
        );
    }

    /**
     * @return array{number: int, title: string, summary: string, metrics: list<array{label: string, value: string}>, note: string}
     */
    private function expiries(CarbonInterface $now): array
    {
        $expired = 0;
        $soon = 0;
        $horizon = $now->copy()->addDays(90)->endOfMonth();
        $current = $now->copy()->startOfMonth();

        InventoryLotBalance::query()
            ->where('quantity_remaining', '>', 0)
            ->whereHas('productLot', fn ($query) => $query->whereNotNull('expiration_month_year'))
            ->with('productLot:id,expiration_month_year')
            ->select(['id', 'product_lot_id', 'quantity_remaining'])
            ->orderBy('id')
            ->chunkById(500, function ($balances) use (&$expired, &$soon, $current, $horizon): void {
                foreach ($balances as $balance) {
                    $expiry = $this->parseMonthYear($balance->productLot?->expiration_month_year);
                    if ($expiry === null) {
                        continue;
                    }

                    if ($expiry->lt($current)) {
                        $expired++;
                    } elseif ($expiry->lte($horizon)) {
                        $soon++;
                    }
                }
            });

        $negative = Inventory::query()->where('quantity', '<', 0)->count();

        return $this->card(
            8,
            'Vencimientos, pérdidas y faltantes',
            'Lotes con existencia que ya vencieron o vencen en 90 días, y filas de inventario en negativo.',
            [
                ['label' => 'Lotes vencidos', 'value' => (string) $expired],
                ['label' => 'Por vencer (90 días)', 'value' => (string) $soon],
                ['label' => 'Inventario en negativo', 'value' => (string) $negative],
            ],
            'Cuenta lotes con saldo, no unidades sueltas.',
        );
    }

    /**
     * @return array{number: int, title: string, summary: string, metrics: list<array{label: string, value: string}>, note: string}
     */
    private function margin(CarbonInterface $monthStart, CarbonInterface $monthEnd): array
    {
        $totals = SaleItem::query()
            ->whereHas('sale', function ($query) use ($monthStart, $monthEnd): void {
                $query->where('status', SaleStatus::Completed->value)
                    ->whereBetween('sold_at', [$monthStart, $monthEnd]);
            })
            ->selectRaw('COALESCE(SUM(line_total), 0) as sales, COALESCE(SUM(line_cost_total), 0) as cost')
            ->first();

        $sales = round((float) ($totals->sales ?? 0), 2);
        $cost = round((float) ($totals->cost ?? 0), 2);
        $margin = round($sales - $cost, 2);
        $percent = $sales > 0.00001 ? round(($margin / $sales) * 100, 1) : 0.0;

        return $this->card(
            9,
            'Margen por convenio / cliente',
            'Venta neta del mes menos el costo de los productos vendidos.',
            [
                ['label' => 'Venta del mes', 'value' => $this->usd($sales)],
                ['label' => 'Costo de productos', 'value' => $this->usd($cost)],
                ['label' => 'Margen', 'value' => $this->usd($margin).' · '.number_format($percent, 1, ',', '.').' %'],
            ],
            'El despacho y otros costos de entrega no están restados: no viajan en la línea de la venta.',
        );
    }

    /**
     * @param  list<array{label: string, value: string}>  $metrics
     * @return array{number: int, title: string, summary: string, metrics: list<array{label: string, value: string}>, note: string}
     */
    private function card(int $number, string $title, string $summary, array $metrics, string $note): array
    {
        return [
            'number' => $number,
            'title' => $title,
            'summary' => $summary,
            'metrics' => $metrics,
            'note' => $note,
        ];
    }

    private function usd(float $amount): string
    {
        return '$ '.number_format($amount, 2, ',', '.');
    }

    private function ves(float $amount): string
    {
        return 'Bs '.number_format($amount, 2, ',', '.');
    }

    private function parseMonthYear(mixed $value): ?Carbon
    {
        if (! is_string($value) || ! preg_match('/^(0[1-9]|1[0-2])\/(\d{4})$/', trim($value), $matches)) {
            return null;
        }

        return Carbon::createFromDate((int) $matches[2], (int) $matches[1], 1)->startOfMonth();
    }
}
