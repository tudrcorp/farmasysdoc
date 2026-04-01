<?php

namespace App\Services\Sales;

use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Models\User;
use App\Support\Filament\BranchAuthScope;
use App\Support\Filament\SaleEffectiveDateScope;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;

/**
 * Arma los datos del PDF de cierre de caja (ventas completadas en un rango de fechas efectivas).
 */
final class CashRegisterCloseReportBuilder
{
    /**
     * @return array{
     *     generated_at: string,
     *     app_name: string,
     *     period_from: string,
     *     period_until: string,
     *     period_full_label: string,
     *     generated_by: string,
     *     scope_note: string,
     *     sales: Collection<int, Sale>,
     *     summary: array{
     *         sale_count: int,
     *         subtotal: float,
     *         tax_total: float,
     *         discount_total: float,
     *         grand_total: float,
     *         payment_usd_sum: float,
     *         payment_ves_sum: float,
     *         items_count: int,
     *         quantity_sold: float,
     *         gross_profit_sum: float,
     *     },
     *     payment_breakdown: list<array{
     *         method: string,
     *         label: string,
     *         count: int,
     *         total_document: float,
     *         payment_usd: float,
     *         payment_ves: float,
     *     }>,
     *     payment_breakdown_totals: array{
     *         count: int,
     *         total_document: float,
     *         payment_usd: float,
     *         payment_ves: float,
     *     },
     * }
     */
    public function build(Carbon $from, Carbon $until): array
    {
        $from = $from->copy()->startOfDay();
        $until = $until->copy()->endOfDay();

        $query = Sale::query()
            ->with([
                'items' => fn ($q) => $q->orderBy('id'),
                'branch',
                'client',
            ])
            ->where('status', SaleStatus::Completed)
            ->orderByRaw('COALESCE(sold_at, created_at) ASC')
            ->orderBy('id');

        BranchAuthScope::apply($query);
        SaleEffectiveDateScope::apply($query, $from->toDateString(), $until->toDateString());

        /** @var Collection<int, Sale> $sales */
        $sales = $query->get();

        $grossProfit = round((float) $sales->sum(function (Sale $sale): float {
            return (float) $sale->items->sum(fn ($item): float => (float) ($item->gross_profit ?? 0));
        }), 2);

        $summary = [
            'sale_count' => $sales->count(),
            'subtotal' => round((float) $sales->sum('subtotal'), 2),
            'tax_total' => round((float) $sales->sum('tax_total'), 2),
            'discount_total' => round((float) $sales->sum('discount_total'), 2),
            'grand_total' => round((float) $sales->sum('total'), 2),
            'payment_usd_sum' => round((float) $sales->sum('payment_usd'), 2),
            'payment_ves_sum' => round((float) $sales->sum('payment_ves'), 2),
            'items_count' => (int) $sales->sum(fn (Sale $s): int => $s->items->count()),
            'quantity_sold' => round((float) $sales->sum(fn (Sale $s): float => (float) $s->items->sum('quantity')), 3),
            'gross_profit_sum' => $grossProfit,
        ];

        /** @var Collection<string, Collection<int, Sale>> $paymentGroups */
        $paymentGroups = $sales->groupBy(fn (Sale $s): string => (string) ($s->payment_method ?? ''));

        $payment_breakdown = [];
        foreach ($paymentGroups as $method => $group) {
            $payment_breakdown[] = [
                'method' => (string) $method,
                'label' => self::paymentMethodLabel((string) $method),
                'count' => $group->count(),
                'total_document' => round((float) $group->sum('total'), 2),
                'payment_usd' => round((float) $group->sum('payment_usd'), 2),
                'payment_ves' => round((float) $group->sum('payment_ves'), 2),
            ];
        }

        usort($payment_breakdown, fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

        $breakdownCollection = collect($payment_breakdown);
        $payment_breakdown_totals = [
            'count' => (int) $breakdownCollection->sum('count'),
            'total_document' => round((float) $breakdownCollection->sum('total_document'), 2),
            'payment_usd' => round((float) $breakdownCollection->sum('payment_usd'), 2),
            'payment_ves' => round((float) $breakdownCollection->sum('payment_ves'), 2),
        ];

        $actor = Auth::user();
        $actorUser = $actor instanceof User ? $actor : null;
        $scope_note = $this->resolveScopeNote($actorUser, $sales);

        return [
            'generated_at' => now()->format('d/m/Y H:i:s'),
            'app_name' => (string) config('app.name'),
            'period_from' => $from->format('d/m/Y'),
            'period_until' => $until->format('d/m/Y'),
            'period_full_label' => $from->isSameDay($until)
                ? $from->format('d/m/Y')
                : $from->format('d/m/Y').' — '.$until->format('d/m/Y'),
            'generated_by' => $actorUser instanceof User
                ? (filled($actorUser->email) ? (string) $actorUser->email : (string) ($actorUser->name ?? '—'))
                : '—',
            'scope_note' => $scope_note,
            'sales' => $sales,
            'summary' => $summary,
            'payment_breakdown' => $payment_breakdown,
            'payment_breakdown_totals' => $payment_breakdown_totals,
        ];
    }

    private function resolveScopeNote(?User $user, Collection $sales): string
    {
        if ($user instanceof User && $user->isAdministrator()) {
            return 'Todas las sucursales (usuario administrador).';
        }

        $branchName = $user?->branch?->name;
        if (filled($branchName)) {
            return 'Sucursal: '.$branchName;
        }

        $first = $sales->first();
        if ($first instanceof Sale && $first->branch !== null) {
            return 'Sucursal: '.(string) $first->branch->name;
        }

        return 'Sucursal según usuario actual.';
    }

    private static function paymentMethodLabel(string $value): string
    {
        if ($value === '') {
            return '—';
        }

        $key = strtolower(trim($value));

        return match ($key) {
            'efectivo_usd' => 'Efectivo USD',
            'efectivo_ves' => 'Efectivo VES',
            'transfer_ves' => 'Transferencia VES',
            'zelle' => 'Zelle',
            'pago_movil' => 'Pago Movil',
            'mixed' => 'Pago Multiple',
            'transfer_usd' => 'Transferencias USD',
            default => $value,
        };
    }
}
