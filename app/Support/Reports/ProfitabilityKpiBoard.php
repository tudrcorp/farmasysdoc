<?php

namespace App\Support\Reports;

use App\Enums\OrderStatus;
use App\Enums\SaleStatus;
use App\Models\Branch;
use App\Models\HrPayrollReceipt;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Sale;
use App\Models\SaleItem;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

final class ProfitabilityKpiBoard
{
    /**
     * @return array{
     *     period_label: string,
     *     compare_label: string,
     *     cards: list<array{number: int, title: string, summary: string, metrics: list<array{label: string, value: string, tone?: string}>, note: string}>
     * }
     */
    public function snapshot(?int $branchId, string $channel, CarbonInterface $month): array
    {
        $month = Carbon::parse($month)->startOfMonth();
        $previous = $month->copy()->subMonth();
        $current = $this->periodFigures($branchId, $channel, $month);
        $prior = $this->periodFigures($branchId, $channel, $previous);

        $growth = $prior['net'] > 0.00001
            ? round((($current['net'] - $prior['net']) / $prior['net']) * 100, 1)
            : null;
        $margin = round($current['net'] - $current['cogs'], 2);
        $marginPercent = $current['net'] > 0.00001 ? round(($margin / $current['net']) * 100, 1) : 0.0;
        $ticket = $current['invoices'] > 0 ? round($current['net'] / $current['invoices'], 2) : 0.0;
        $payroll = $this->payrollVes($branchId, $month);

        return [
            'period_label' => $month->locale('es')->isoFormat('MMMM YYYY'),
            'compare_label' => $previous->locale('es')->isoFormat('MMMM YYYY'),
            'cards' => [
                $this->card(1, 'Venta neta', 'Ventas menos descuentos, sin impuestos. Las canceladas y reembolsadas no entran.', [
                    ['label' => 'Venta neta', 'value' => $this->usd($current['net'])],
                    ['label' => 'Descuentos', 'value' => $this->usd($current['discounts'])],
                    ['label' => 'Facturas', 'value' => (string) $current['invoices']],
                ], 'Mostrador: subtotal menos descuento. Convenios: total del pedido menos IVA.'),
                $this->card(2, 'Ventas corporativas / mayoristas', 'Pedidos de convenio finalizados, por el aliado de mayor venta en el período.', [
                    ['label' => 'Venta de convenios', 'value' => $this->usd($current['agreement_net'])],
                    ['label' => 'Pedidos', 'value' => (string) $current['agreement_invoices']],
                    ['label' => 'Mayor convenio', 'value' => $current['top_partner'], 'tone' => 'text'],
                ], 'Un convenio es un pedido con empresa aliada. No se mezcla con la venta de mostrador.'),
                $this->card(3, 'Venta neta consolidada', 'Mostrador más convenios. Cada operación está en una sola tabla.', [
                    ['label' => 'Mostrador', 'value' => $this->usd($current['counter_net'])],
                    ['label' => 'Convenios', 'value' => $this->usd($current['agreement_net'])],
                    ['label' => 'Consolidado', 'value' => $this->usd($current['counter_net'] + $current['agreement_net']), 'tone' => 'up'],
                ], 'El filtro de canal muestra solo una de las dos partes. El consolidado de esta tarjeta siempre suma ambas.'),
                $this->card(4, 'Crecimiento de ventas', 'Período actual contra el mes anterior, con el mismo filtro de sucursal y canal.', [
                    ['label' => 'Este período', 'value' => $this->usd($current['net'])],
                    ['label' => 'Período anterior', 'value' => $this->usd($prior['net'])],
                    ['label' => 'Variación', 'value' => $growth === null ? 'Sin base' : $this->signedPercent($growth), 'tone' => $growth === null ? 'text' : ($growth < 0 ? 'down' : 'up')],
                ], 'Comparado con '.$previous->locale('es')->isoFormat('MMMM YYYY').'.'),
                $this->card(5, 'Ticket promedio', 'Venta neta dividida entre facturas o pedidos completados.', [
                    ['label' => 'Ticket', 'value' => $this->usd($ticket)],
                    ['label' => 'Mostrador', 'value' => $this->usd($current['counter_ticket'])],
                    ['label' => 'Convenios', 'value' => $this->usd($current['agreement_ticket'])],
                ], $current['branch_ticket_note']),
                $this->card(6, 'Costo de ventas', 'Costo de lo vendido en el período, no de las compras del mes.', [
                    ['label' => 'Costo mostrador', 'value' => $this->usd($current['counter_cogs'])],
                    ['label' => 'Costo convenios', 'value' => $this->usd($current['agreement_cogs'])],
                    ['label' => 'Costo total', 'value' => $this->usd($current['cogs'])],
                ], 'En mostrador es el costo guardado en la venta. En convenios es la cantidad por el costo actual del producto.'),
                $this->card(7, 'Margen bruto', 'Venta neta del filtro menos el costo de esas ventas.', [
                    ['label' => 'Venta neta', 'value' => $this->usd($current['net'])],
                    ['label' => 'Costo', 'value' => $this->usd($current['cogs'])],
                    ['label' => 'Margen', 'value' => $this->usd($margin).' · '.number_format($marginPercent, 1, ',', '.').' %', 'tone' => $margin < 0 ? 'down' : 'up'],
                ], 'No resta nómina, alquiler ni despacho.'),
                $this->card(8, 'Gastos operativos', 'Nómina devengada del mes. Alquiler, servicios y caja chica no tienen registro.', [
                    ['label' => 'Nómina', 'value' => $this->ves($payroll)],
                    ['label' => 'Alquiler y servicios', 'value' => 'Sin registro', 'tone' => 'text'],
                    ['label' => 'Caja chica', 'value' => 'Sin registro', 'tone' => 'text'],
                ], 'La nómina queda en bolívares. No se convierte a dólares.'),
                $this->card(9, 'Resultado operativo', 'Margen bruto en dólares y nómina en bolívares, sin restarlos entre sí.', [
                    ['label' => 'Margen bruto', 'value' => $this->usd($margin), 'tone' => $margin < 0 ? 'down' : 'up'],
                    ['label' => 'Nómina del mes', 'value' => $this->ves($payroll)],
                ], 'Por sucursal y canal cuando el filtro lo pide. El resultado en una sola moneda espera gastos fijos en dólares.'),
                $this->card(10, 'Punto de equilibrio', 'Ventas necesarias para cubrir gastos fijos, según el margen de contribución.', [
                    ['label' => 'Margen de contribución', 'value' => number_format($marginPercent, 1, ',', '.').' %'],
                    ['label' => 'Gastos fijos en USD', 'value' => 'Sin registro', 'tone' => 'text'],
                    ['label' => 'Equilibrio', 'value' => 'No calculable', 'tone' => 'text'],
                ], 'Hace falta alquiler, servicios y caja chica en dólares. La nómina en bolívares no se usa como divisor.'),
            ],
        ];
    }

    /**
     * @return array{
     *     net: float, discounts: float, invoices: int, cogs: float,
     *     counter_net: float, counter_invoices: int, counter_cogs: float, counter_ticket: float,
     *     agreement_net: float, agreement_invoices: int, agreement_cogs: float, agreement_ticket: float,
     *     top_partner: string, branch_ticket_note: string
     * }
     */
    private function periodFigures(?int $branchId, string $channel, CarbonInterface $month): array
    {
        $includeCounter = $channel !== 'agreement';
        $includeAgreement = $channel !== 'counter';
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $counter = $includeCounter ? $this->counterFigures($branchId, $start, $end) : $this->emptyChannel();
        $agreement = $includeAgreement ? $this->agreementFigures($branchId, $start, $end) : $this->emptyChannel();

        return [
            'net' => round($counter['net'] + $agreement['net'], 2),
            'discounts' => round($counter['discounts'] + $agreement['discounts'], 2),
            'invoices' => $counter['invoices'] + $agreement['invoices'],
            'cogs' => round($counter['cogs'] + $agreement['cogs'], 2),
            'counter_net' => $counter['net'],
            'counter_invoices' => $counter['invoices'],
            'counter_cogs' => $counter['cogs'],
            'counter_ticket' => $counter['invoices'] > 0 ? round($counter['net'] / $counter['invoices'], 2) : 0.0,
            'agreement_net' => $agreement['net'],
            'agreement_invoices' => $agreement['invoices'],
            'agreement_cogs' => $agreement['cogs'],
            'agreement_ticket' => $agreement['invoices'] > 0 ? round($agreement['net'] / $agreement['invoices'], 2) : 0.0,
            'top_partner' => $agreement['top_partner'],
            'branch_ticket_note' => $this->branchTicketNote($branchId, $start, $end, $includeCounter),
        ];
    }

    /**
     * @return array{net: float, discounts: float, invoices: int, cogs: float, top_partner: string}
     */
    private function counterFigures(?int $branchId, CarbonInterface $start, CarbonInterface $end): array
    {
        $row = $this->salesQuery($branchId, $start, $end)
            ->selectRaw('COUNT(*) as invoices, COALESCE(SUM(subtotal), 0) as subtotal, COALESCE(SUM(discount_total), 0) as discounts')
            ->first();
        $subtotal = round((float) ($row->subtotal ?? 0), 2);
        $discounts = round((float) ($row->discounts ?? 0), 2);
        $cogs = round((float) SaleItem::query()
            ->whereHas('sale', fn (Builder $query) => $this->constrainSales($query, $branchId, $start, $end))
            ->sum('line_cost_total'), 2);

        return [
            'net' => round($subtotal - $discounts, 2),
            'discounts' => $discounts,
            'invoices' => (int) ($row->invoices ?? 0),
            'cogs' => $cogs,
            'top_partner' => '—',
        ];
    }

    /**
     * @return array{net: float, discounts: float, invoices: int, cogs: float, top_partner: string}
     */
    private function agreementFigures(?int $branchId, CarbonInterface $start, CarbonInterface $end): array
    {
        $row = $this->ordersQuery($branchId, $start, $end)
            ->selectRaw('COUNT(*) as invoices, COALESCE(SUM(total), 0) as total, COALESCE(SUM(tax_total), 0) as tax, COALESCE(SUM(discount_total), 0) as discounts')
            ->first();
        $net = round(max(0, (float) ($row->total ?? 0) - (float) ($row->tax ?? 0)), 2);
        $cogs = round((float) OrderItem::query()
            ->join('products', 'products.id', '=', 'order_items.product_id')
            ->whereHas('order', fn (Builder $query) => $this->constrainOrders($query, $branchId, $start, $end))
            ->selectRaw('COALESCE(SUM(order_items.quantity * products.cost_price), 0) as cogs')
            ->value('cogs'), 2);

        $top = $this->ordersQuery($branchId, $start, $end)
            ->join('partner_companies', 'partner_companies.id', '=', 'orders.partner_company_id')
            ->selectRaw("COALESCE(NULLIF(partner_companies.trade_name, ''), partner_companies.legal_name) as partner_name, COALESCE(SUM(orders.total - orders.tax_total), 0) as net")
            ->groupByRaw("COALESCE(NULLIF(partner_companies.trade_name, ''), partner_companies.legal_name)")
            ->orderByDesc('net')
            ->first();

        return [
            'net' => $net,
            'discounts' => round((float) ($row->discounts ?? 0), 2),
            'invoices' => (int) ($row->invoices ?? 0),
            'cogs' => $cogs,
            'top_partner' => filled($top->partner_name ?? null) ? (string) $top->partner_name : 'Sin convenios',
        ];
    }

    /**
     * @return array{net: float, discounts: float, invoices: int, cogs: float, top_partner: string}
     */
    private function emptyChannel(): array
    {
        return [
            'net' => 0.0,
            'discounts' => 0.0,
            'invoices' => 0,
            'cogs' => 0.0,
            'top_partner' => '—',
        ];
    }

    private function branchTicketNote(?int $branchId, CarbonInterface $start, CarbonInterface $end, bool $includeCounter): string
    {
        if ($branchId !== null || ! $includeCounter) {
            return 'El ticket de convenio usa pedidos finalizados del aliado.';
        }

        $rows = $this->salesQuery(null, $start, $end)
            ->join('branches', 'branches.id', '=', 'sales.branch_id')
            ->selectRaw('branches.name as branch_name, COUNT(*) as invoices, COALESCE(SUM(sales.subtotal - sales.discount_total), 0) as net')
            ->groupBy('branches.name')
            ->orderByDesc('net')
            ->limit(3)
            ->get();

        if ($rows->isEmpty()) {
            return 'No hay ventas de mostrador en el período.';
        }

        $parts = $rows->map(function (object $row): string {
            $invoices = max(1, (int) $row->invoices);
            $ticket = round(((float) $row->net) / $invoices, 2);

            return $row->branch_name.': '.$this->usd($ticket);
        });

        return 'Ticket de mostrador · '.$parts->implode(' · ');
    }

    private function payrollVes(?int $branchId, CarbonInterface $month): float
    {
        $query = HrPayrollReceipt::query()
            ->where('year', (int) $month->year)
            ->where('month', (int) $month->month);

        if ($branchId !== null) {
            $name = Branch::query()->whereKey($branchId)->value('name');
            if (! is_string($name) || $name === '') {
                return 0.0;
            }

            $query->where('branch_name', $name);
        }

        return round((float) $query->sum('assignments_ves'), 2);
    }

    /**
     * @return Builder<Sale>
     */
    private function salesQuery(?int $branchId, CarbonInterface $start, CarbonInterface $end): Builder
    {
        return $this->constrainSales(Sale::query(), $branchId, $start, $end);
    }

    /**
     * @param  Builder<Sale>  $query
     * @return Builder<Sale>
     */
    private function constrainSales(Builder $query, ?int $branchId, CarbonInterface $start, CarbonInterface $end): Builder
    {
        return $query
            ->where('sales.status', SaleStatus::Completed->value)
            ->whereBetween('sales.sold_at', [$start, $end])
            ->when($branchId !== null, fn (Builder $inner) => $inner->where('sales.branch_id', $branchId));
    }

    /**
     * @return Builder<Order>
     */
    private function ordersQuery(?int $branchId, CarbonInterface $start, CarbonInterface $end): Builder
    {
        return $this->constrainOrders(Order::query(), $branchId, $start, $end);
    }

    /**
     * @param  Builder<Order>  $query
     * @return Builder<Order>
     */
    private function constrainOrders(Builder $query, ?int $branchId, CarbonInterface $start, CarbonInterface $end): Builder
    {
        return $query
            ->where('orders.status', OrderStatus::Completed->value)
            ->whereNotNull('orders.partner_company_id')
            ->whereBetween('orders.created_at', [$start, $end])
            ->when($branchId !== null, fn (Builder $inner) => $inner->where('orders.branch_id', $branchId));
    }

    /**
     * @param  list<array{label: string, value: string, tone?: string}>  $metrics
     * @return array{number: int, title: string, summary: string, metrics: list<array{label: string, value: string, tone?: string}>, note: string}
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

    private function signedPercent(float $percent): string
    {
        $formatted = number_format(abs($percent), 1, ',', '.').' %';

        return $percent < 0 ? '− '.$formatted : '+ '.$formatted;
    }
}
