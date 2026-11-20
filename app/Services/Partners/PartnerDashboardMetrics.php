<?php

namespace App\Services\Partners;

use App\Enums\OrderStatus;
use App\Enums\PartnerAgreementSaleStatus;
use App\Enums\PartnerFundMovementType;
use App\Models\HistoricalOfMovement;
use App\Models\Order;
use App\Models\PartnerAgreementSale;
use App\Models\PartnerCompany;
use App\Models\PartnerCompanyAgreement;
use App\Models\PartnerCompanyPlan;
use App\Models\PartnerFundMovement;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * Métricas del tablero de dale · Aliados. Todo se calcula con pocas consultas agregadas y se
 * guarda en caché por aliado: los N analistas de un mismo aliado comparten el mismo cálculo.
 * La caché se invalida al cambiar pedidos, movimientos, convenios, planes o la ficha del aliado.
 */
class PartnerDashboardMetrics
{
    public const CACHE_SECONDS = 60;

    public const CACHE_VERSION = 4;

    public const MONTHS = 12;

    public const TOP_PRODUCTS_DAYS = 90;

    /**
     * @return array<string, mixed>
     */
    public function for(int $partnerId): array
    {
        return Cache::remember(self::cacheKey($partnerId), self::CACHE_SECONDS, fn (): array => $this->compute($partnerId));
    }

    public static function forget(?int $partnerId): void
    {
        if ($partnerId !== null && $partnerId > 0) {
            Cache::forget(self::cacheKey($partnerId));
        }
    }

    public static function cacheKey(int $partnerId): string
    {
        return 'partner-dashboard:v'.self::CACHE_VERSION.':'.$partnerId;
    }

    public static function registerCacheInvalidation(): void
    {
        $byPartnerColumn = fn (Model $model) => self::forget(is_numeric($model->getAttribute('partner_company_id')) ? (int) $model->getAttribute('partner_company_id') : null);

        foreach ([Order::class, HistoricalOfMovement::class, PartnerCompanyAgreement::class, PartnerCompanyPlan::class, PartnerFundMovement::class, PartnerAgreementSale::class] as $modelClass) {
            $modelClass::saved($byPartnerColumn);
            $modelClass::deleted($byPartnerColumn);
        }

        PartnerCompany::saved(fn (PartnerCompany $partner) => self::forget((int) $partner->getKey()));
    }

    /**
     * @return array<string, mixed>
     */
    private function compute(int $partnerId): array
    {
        $partner = PartnerCompany::query()->with('currentAgreement')->find($partnerId);

        if (! $partner instanceof PartnerCompany) {
            return [];
        }

        $now = CarbonImmutable::now();
        $term = $partner->agreementTermStatus();

        return [
            'generated_at' => $now->toIso8601String(),
            'partner' => [
                'name' => (string) ($partner->trade_name ?: $partner->legal_name),
                'legal_name' => (string) $partner->legal_name,
                'modality' => $partner->payment_modality?->label(),
            ],
            'term' => [
                'label' => $term->label,
                'tone' => $term->tone,
                'remaining_days' => $term->remainingDays,
                'total_days' => $term->totalDays,
                'progress' => $term->progressPercent(),
                'starts_on' => $term->startsOn?->format('d/m/Y'),
                'ends_on' => $term->endsOn?->format('d/m/Y'),
            ],
            'credit' => $this->credit($partner),
            'fund' => $this->fund($partner),
            'month' => $this->monthComparison($partnerId, $now),
            'series' => $this->monthlySeries($partnerId, $now),
            'statuses' => $this->statusCounts($partnerId),
            'top_products' => $this->topProducts($partnerId, $now),
            'recent_activity' => $this->recentActivity($partnerId),
            'plans_count' => $partner->plans()->count(),
        ];
    }

    /**
     * @return array{manages: bool, available: float, consumed: float, ceiling: float, used_percent: ?float}|null
     */
    private function credit(PartnerCompany $partner): ?array
    {
        if (! $partner->managesAssignedCredit()) {
            return null;
        }

        $available = $partner->remainingCreditAmount();
        $consumed = $partner->totalCreditConsumedAmount();
        $ceiling = round($available + $consumed, 2);

        return [
            'manages' => true,
            'available' => $available,
            'consumed' => $consumed,
            'ceiling' => $ceiling,
            'used_percent' => $ceiling > 0 ? round(min(100, $consumed / $ceiling * 100), 2) : null,
        ];
    }

    /**
     * Fondo de consumo (planes Flexible): autorizado + recargas frente a lo consumido en notas de
     * entrega (las anuladas se reintegran). Sale del libro `partner_fund_movements`.
     *
     * @return array{authorized: float, consumed: float, remaining: float, used_percent: float, since: ?string}|null
     */
    private function fund(PartnerCompany $partner): ?array
    {
        $assignments = PartnerAffiliateService::flexibleAssignmentsQuery()
            ->where('partner_company_id', $partner->getKey())
            ->get(['id', 'authorized_amount_usd', 'created_at']);

        if ($assignments->isEmpty()) {
            return null;
        }

        $totals = PartnerFundMovement::query()
            ->whereIn('partner_company_plan_id', $assignments->modelKeys())
            ->selectRaw('type, SUM(amount_usd) AS total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $authorized = (float) $assignments->sum(fn (PartnerCompanyPlan $assignment): float => (float) $assignment->authorized_amount_usd)
            + (float) ($totals[PartnerFundMovementType::Recharge->value] ?? 0);
        $consumed = abs((float) ($totals[PartnerFundMovementType::Consumption->value] ?? 0)) - (float) ($totals[PartnerFundMovementType::Reversal->value] ?? 0);

        if ($authorized <= 0) {
            return null;
        }

        return [
            'authorized' => round($authorized, 2),
            'consumed' => round($consumed, 2),
            'remaining' => round(max(0, $authorized - $consumed), 2),
            'used_percent' => round(min(100, max(0, $consumed / $authorized * 100)), 2),
            'since' => $assignments->min('created_at')?->format('d/m/Y'),
        ];
    }

    /**
     * Movimientos del mes y del mes anterior: pedidos más consumos por convenio (notas de entrega
     * aplicadas). `orders`/`amount` son la suma de ambos; se separan en `order_count` y `consumptions`.
     *
     * @return array{orders: int, amount: float, previous_orders: int, previous_amount: float, order_count: int, consumptions: int, consumption_amount: float}
     */
    private function monthComparison(int $partnerId, CarbonImmutable $now): array
    {
        $current = $now->startOfMonth();
        $previous = $current->subMonth();

        $orders = Order::query()
            ->where('partner_company_id', $partnerId)
            ->where('created_at', '>=', $previous)
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN 1 ELSE 0 END) AS current_count', [$current])
            ->selectRaw('SUM(CASE WHEN created_at >= ? THEN total ELSE 0 END) AS current_amount', [$current])
            ->selectRaw('SUM(CASE WHEN created_at < ? THEN 1 ELSE 0 END) AS previous_count', [$current])
            ->selectRaw('SUM(CASE WHEN created_at < ? THEN total ELSE 0 END) AS previous_amount', [$current])
            ->toBase()
            ->first();

        $consumptions = PartnerAgreementSale::query()
            ->where('partner_company_id', $partnerId)
            ->where('status', PartnerAgreementSaleStatus::Completed->value)
            ->where('sold_at', '>=', $previous)
            ->selectRaw('SUM(CASE WHEN sold_at >= ? THEN 1 ELSE 0 END) AS current_count', [$current])
            ->selectRaw('SUM(CASE WHEN sold_at >= ? THEN total ELSE 0 END) AS current_amount', [$current])
            ->selectRaw('SUM(CASE WHEN sold_at < ? THEN 1 ELSE 0 END) AS previous_count', [$current])
            ->selectRaw('SUM(CASE WHEN sold_at < ? THEN total ELSE 0 END) AS previous_amount', [$current])
            ->toBase()
            ->first();

        $orderCount = (int) ($orders->current_count ?? 0);
        $consumptionCount = (int) ($consumptions->current_count ?? 0);
        $consumptionAmount = round((float) ($consumptions->current_amount ?? 0), 2);

        return [
            'orders' => $orderCount + $consumptionCount,
            'amount' => round((float) ($orders->current_amount ?? 0) + $consumptionAmount, 2),
            'previous_orders' => (int) ($orders->previous_count ?? 0) + (int) ($consumptions->previous_count ?? 0),
            'previous_amount' => round((float) ($orders->previous_amount ?? 0) + (float) ($consumptions->previous_amount ?? 0), 2),
            'order_count' => $orderCount,
            'consumptions' => $consumptionCount,
            'consumption_amount' => $consumptionAmount,
        ];
    }

    /**
     * Movimientos por mes (últimos 12 meses): pedidos y consumos por convenio por separado.
     *
     * @return array{labels: list<string>, orders: list<int>, amounts: list<float>, completed_amounts: list<float>, consumptions: list<int>, consumption_amounts: list<float>}
     */
    private function monthlySeries(int $partnerId, CarbonImmutable $now): array
    {
        $start = $now->startOfMonth()->subMonths(self::MONTHS - 1);

        $orderRows = Order::query()
            ->where('partner_company_id', $partnerId)
            ->where('created_at', '>=', $start)
            ->selectRaw($this->monthExpression('created_at').' AS ym')
            ->selectRaw('COUNT(*) AS orders')
            ->selectRaw('SUM(total) AS amount')
            ->selectRaw('SUM(CASE WHEN status = ? THEN total ELSE 0 END) AS completed_amount', [OrderStatus::Completed->value])
            ->groupBy('ym')
            ->toBase()
            ->get()
            ->keyBy('ym');

        $consumptionRows = PartnerAgreementSale::query()
            ->where('partner_company_id', $partnerId)
            ->where('status', PartnerAgreementSaleStatus::Completed->value)
            ->where('sold_at', '>=', $start)
            ->selectRaw($this->monthExpression('sold_at').' AS ym')
            ->selectRaw('COUNT(*) AS consumptions')
            ->selectRaw('SUM(total) AS amount')
            ->groupBy('ym')
            ->toBase()
            ->get()
            ->keyBy('ym');

        $series = ['labels' => [], 'orders' => [], 'amounts' => [], 'completed_amounts' => [], 'consumptions' => [], 'consumption_amounts' => []];

        for ($i = 0; $i < self::MONTHS; $i++) {
            $date = $start->addMonths($i);
            $key = $date->format('Y-m');
            $order = $orderRows->get($key);
            $consumption = $consumptionRows->get($key);

            $series['labels'][] = ucfirst($date->locale('es')->translatedFormat('M y'));
            $series['orders'][] = (int) ($order->orders ?? 0);
            $series['amounts'][] = round((float) ($order->amount ?? 0), 2);
            $series['completed_amounts'][] = round((float) ($order->completed_amount ?? 0), 2);
            $series['consumptions'][] = (int) ($consumption->consumptions ?? 0);
            $series['consumption_amounts'][] = round((float) ($consumption->amount ?? 0), 2);
        }

        return $series;
    }

    /**
     * @return list<array{status: string, label: string, color: string, count: int}>
     */
    private function statusCounts(int $partnerId): array
    {
        $counts = Order::query()
            ->where('partner_company_id', $partnerId)
            ->selectRaw('status, COUNT(*) AS aggregate')
            ->groupBy('status')
            ->toBase()
            ->pluck('aggregate', 'status');

        return array_map(fn (OrderStatus $status): array => [
            'status' => $status->value,
            'label' => $status->label(),
            'color' => match ($status) {
                OrderStatus::Pending => 'warning',
                OrderStatus::Accepted => 'primary',
                OrderStatus::InProgress => 'info',
                OrderStatus::Completed => 'success',
            },
            'count' => (int) ($counts[$status->value] ?? 0),
        ], OrderStatus::cases());
    }

    /**
     * Productos más solicitados en los últimos 90 días: pedidos y consumos por convenio.
     *
     * @return list<array{name: string, quantity: float, amount: float}>
     */
    private function topProducts(int $partnerId, CarbonImmutable $now): array
    {
        $since = $now->subDays(self::TOP_PRODUCTS_DAYS)->startOfDay();

        $fromOrders = DB::table('order_items')
            ->join('orders', 'orders.id', '=', 'order_items.order_id')
            ->where('orders.partner_company_id', $partnerId)
            ->where('orders.created_at', '>=', $since)
            ->selectRaw('order_items.product_id, MAX(order_items.product_name_snapshot) AS name, SUM(order_items.quantity) AS quantity, SUM(order_items.line_total) AS amount')
            ->groupBy('order_items.product_id')
            ->get();

        $fromConsumptions = DB::table('partner_agreement_sale_items')
            ->join('partner_agreement_sales', 'partner_agreement_sales.id', '=', 'partner_agreement_sale_items.partner_agreement_sale_id')
            ->where('partner_agreement_sales.partner_company_id', $partnerId)
            ->where('partner_agreement_sales.status', PartnerAgreementSaleStatus::Completed->value)
            ->where('partner_agreement_sales.sold_at', '>=', $since)
            ->selectRaw('partner_agreement_sale_items.product_id, MAX(partner_agreement_sale_items.product_name_snapshot) AS name, SUM(partner_agreement_sale_items.quantity) AS quantity, SUM(partner_agreement_sale_items.line_total) AS amount')
            ->groupBy('partner_agreement_sale_items.product_id')
            ->get();

        return $fromOrders->concat($fromConsumptions)
            ->groupBy('product_id')
            ->map(fn ($rows): array => [
                'name' => (string) ($rows->first()->name ?: 'Producto'),
                'quantity' => round((float) $rows->sum('quantity'), 2),
                'amount' => round((float) $rows->sum('amount'), 2),
            ])
            ->sortByDesc('quantity')
            ->take(5)
            ->values()
            ->all();
    }

    /**
     * Últimos movimientos: pedidos y notas de entrega por convenio, del más reciente al más antiguo.
     *
     * @return list<array{type: string, id: int, number: string, status: string, label: string, total: float, created_at: string, detail: ?string, timestamp: int}>
     */
    private function recentActivity(int $partnerId): array
    {
        $orders = Order::query()
            ->where('partner_company_id', $partnerId)
            ->latest('created_at')
            ->latest('id')
            ->limit(6)
            ->get(['id', 'order_number', 'status', 'total', 'created_at'])
            ->map(fn (Order $order): array => [
                'type' => 'order',
                'id' => (int) $order->getKey(),
                'number' => (string) $order->order_number,
                'status' => $order->status instanceof OrderStatus ? $order->status->value : (string) $order->status,
                'label' => $order->status instanceof OrderStatus ? $order->status->label() : (string) $order->status,
                'total' => round((float) $order->total, 2),
                'created_at' => $order->created_at?->format('d/m/Y h:i a') ?? '',
                'detail' => 'Pedido',
                'timestamp' => (int) $order->created_at?->getTimestamp(),
            ]);

        $consumptions = PartnerAgreementSale::query()
            ->where('partner_company_id', $partnerId)
            ->latest('sold_at')
            ->latest('id')
            ->limit(6)
            ->get(['id', 'number', 'status', 'total', 'sold_at', 'client_name'])
            ->map(fn (PartnerAgreementSale $sale): array => [
                'type' => 'consumption',
                'id' => (int) $sale->getKey(),
                'number' => (string) $sale->number,
                'status' => $sale->isCancelled() ? 'consumo-anulado' : 'consumo',
                'label' => $sale->isCancelled() ? 'Anulado' : 'Consumo',
                'total' => round((float) $sale->total, 2),
                'created_at' => $sale->sold_at?->format('d/m/Y h:i a') ?? '',
                'detail' => $sale->client_name,
                'timestamp' => (int) $sale->sold_at?->getTimestamp(),
            ]);

        return $orders->concat($consumptions)
            ->sortByDesc('timestamp')
            ->take(6)
            ->values()
            ->all();
    }

    private function monthExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            'pgsql' => "to_char({$column}, 'YYYY-MM')",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }
}
