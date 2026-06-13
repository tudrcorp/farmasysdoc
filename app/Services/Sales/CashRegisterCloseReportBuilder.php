<?php

namespace App\Services\Sales;

use App\Enums\SaleStatus;
use App\Models\Branch;
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
     *         payment_usd: float,
     *         payment_ves: float,
     *     }>,
     *     payment_breakdown_totals: array{
     *         count: int,
     *         payment_usd: float,
     *         payment_ves: float,
     *     },
     * }
     */
    /**
     * @param  list<int>|null  $branchIds  Solo administradores: filtra sucursales. Null = todas las permitidas.
     */
    public function build(Carbon $from, Carbon $until, ?array $branchIds = null): array
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

        BranchAuthScope::applyToSalesQuery($query);
        SaleEffectiveDateScope::apply($query, $from->toDateString(), $until->toDateString());

        if ($branchIds !== null) {
            if ($branchIds === []) {
                $query->whereRaw('1 = 0');
            } else {
                $query->whereIn($query->getModel()->getTable().'.branch_id', $branchIds);
            }
        }

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
                'payment_usd' => round((float) $group->sum(
                    fn (Sale $sale): float => self::resolvedPaymentAmounts($sale)['usd'],
                ), 2),
                'payment_ves' => round((float) $group->sum(
                    fn (Sale $sale): float => self::resolvedPaymentAmounts($sale)['ves'],
                ), 2),
            ];
        }

        usort($payment_breakdown, fn (array $a, array $b): int => strcmp($a['label'], $b['label']));

        $breakdownCollection = collect($payment_breakdown);
        $payment_breakdown_totals = [
            'count' => (int) $breakdownCollection->sum('count'),
            'payment_usd' => round((float) $breakdownCollection->sum('payment_usd'), 2),
            'payment_ves' => round((float) $breakdownCollection->sum('payment_ves'), 2),
        ];

        $actor = Auth::user();
        $actorUser = $actor instanceof User ? $actor : null;
        $selectedBranchNames = $this->resolveSelectedBranchNames($branchIds);
        $scope_note = $this->resolveScopeNote($actorUser, $sales, $branchIds, $selectedBranchNames);

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

    /**
     * @param  list<int>|null  $requestedBranchIds
     * @return list<int>|null null = todas las sucursales; list = filtro válido; [] = inválido
     */
    public function resolveAdministratorBranchFilter(?array $requestedBranchIds): ?array
    {
        $user = Auth::user();
        if (! $user instanceof User || ! $user->isAdministrator()) {
            return null;
        }

        if ($requestedBranchIds === null || $requestedBranchIds === []) {
            return null;
        }

        $permittedIds = BranchAuthScope::applyToBranchFormSelect(
            Branch::query()->where('is_active', true),
        )
            ->pluck('id')
            ->map(fn (mixed $id): int => (int) $id)
            ->all();

        $allowed = array_values(array_intersect(
            array_values(array_unique(array_map('intval', $requestedBranchIds))),
            $permittedIds,
        ));

        return $allowed === [] ? [] : $allowed;
    }

    /**
     * @param  list<int>|null  $branchIds
     * @return list<string>
     */
    private function resolveSelectedBranchNames(?array $branchIds): array
    {
        if ($branchIds === null || $branchIds === []) {
            return [];
        }

        return Branch::query()
            ->whereIn('id', $branchIds)
            ->orderBy('name')
            ->pluck('name')
            ->map(fn (mixed $name): string => (string) $name)
            ->values()
            ->all();
    }

    /**
     * @param  list<int>|null  $selectedBranchIds
     * @param  list<string>  $selectedBranchNames
     */
    private function resolveScopeNote(
        ?User $user,
        Collection $sales,
        ?array $selectedBranchIds,
        array $selectedBranchNames,
    ): string {
        if ($user instanceof User && $user->isAdministrator()) {
            if ($selectedBranchIds === null || $selectedBranchIds === []) {
                return 'Todas las sucursales.';
            }

            if (count($selectedBranchNames) === 1) {
                return 'Sucursal: '.$selectedBranchNames[0].'.';
            }

            if ($selectedBranchNames !== []) {
                return 'Sucursales: '.implode(', ', $selectedBranchNames).'.';
            }

            return 'Sucursales seleccionadas: '.count($selectedBranchIds).'.';
        }

        $branchName = $user?->branch?->name;
        $note = '';
        if (filled($branchName)) {
            $note = 'Sucursal: '.$branchName;
        } else {
            $first = $sales->first();
            if ($first instanceof Sale && $first->branch !== null) {
                $note = 'Sucursal: '.(string) $first->branch->name;
            } else {
                $note = 'Sucursal según usuario actual.';
            }
        }

        if ($user instanceof User && $user->isCashier()) {
            $note .= ' Solo ventas registradas por usted (rol cajero).';
        }

        return $note;
    }

    /**
     * Completa el cobro en la moneda faltante usando la tasa BCV de la venta.
     *
     * @return array{usd: float, ves: float}
     */
    private static function resolvedPaymentAmounts(Sale $sale): array
    {
        $usd = round((float) $sale->payment_usd, 2);
        $ves = round((float) $sale->payment_ves, 2);
        $rate = self::resolveVesUsdRate($sale);

        if ($usd > 0.00001 && $ves <= 0.00001) {
            $ves = round($usd * $rate, 2);
        } elseif ($ves > 0.00001 && $usd <= 0.00001) {
            $usd = round($ves / $rate, 2);
        }

        return [
            'usd' => $usd,
            'ves' => $ves,
        ];
    }

    private static function resolveVesUsdRate(Sale $sale): float
    {
        $stored = (float) ($sale->bcv_ves_per_usd ?? 0);
        if ($stored > 0) {
            return $stored;
        }

        $totalUsd = (float) $sale->total;
        $ves = (float) ($sale->payment_ves ?? 0);

        if ($totalUsd > 0.00001 && $ves > 0) {
            return $ves / $totalUsd;
        }

        $fallback = config('fiscal.fallback_ves_usd_rate');

        return is_numeric($fallback) && (float) $fallback > 0 ? (float) $fallback : 1.0;
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
            'cachea' => 'Cachea',
            'pago_movil' => 'Pago Movil',
            'mixed' => 'Pago Multiple',
            'transfer_usd' => 'Transferencias USD',
            default => $value,
        };
    }
}
