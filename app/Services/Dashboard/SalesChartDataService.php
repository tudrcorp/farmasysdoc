<?php

namespace App\Services\Dashboard;

use App\Enums\SaleStatus;
use App\Models\Sale;
use App\Models\User;
use App\Support\Filament\BranchAuthScope;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

/**
 * Agregados de ventas completadas para gráficos (alcance {@see BranchAuthScope}).
 */
final class SalesChartDataService
{
    private const FILTER_MONTHS_SUMMARY = 'months';

    public static function filterMonthsSummaryKey(): string
    {
        return self::FILTER_MONTHS_SUMMARY;
    }

    /**
     * Totales por cada mes de un año calendario (enero → diciembre).
     *
     * @return array{labels: list<string>, data: list<float>}
     */
    public function totalsForCalendarYear(int $year): array
    {
        $orderedKeys = [];
        $labels = [];

        for ($month = 1; $month <= 12; $month++) {
            $d = now()->setDate($year, $month, 1)->startOfMonth();
            $orderedKeys[] = $d->format('Y-m');
            $labels[] = ucfirst($d->locale('es')->translatedFormat('M'));
        }

        $from = now()->setDate($year, 1, 1)->startOfDay();
        $to = now()->setDate($year, 12, 31)->endOfDay();

        $rows = $this->aggregatedByPeriod($from, $to, $this->monthPeriodExpression(), 'period');

        $data = [];
        foreach ($orderedKeys as $key) {
            $data[] = (float) ($rows[$key] ?? 0);
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * @return array{labels: list<string>, data: list<float>}
     */
    public function totalsByDayInMonth(CarbonInterface $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $rows = $this->aggregatedByPeriod($start, $end, $this->dayPeriodExpression(), 'd');

        $labels = [];
        $data = [];

        for ($day = $start->copy(); $day->lte($end); $day = $day->addDay()) {
            $key = $day->format('Y-m-d');
            $labels[] = $day->locale('es')->translatedFormat('D j');
            $data[] = (float) ($rows[$key] ?? 0);
        }

        return ['labels' => $labels, 'data' => $data];
    }

    /**
     * @return array<string, float|string>
     */
    private function aggregatedByPeriod(CarbonInterface $from, CarbonInterface $to, string $sqlExpression, string $alias): array
    {
        $driver = DB::connection()->getDriverName();

        $totalCast = match ($driver) {
            'sqlite' => 'CAST(total AS REAL)',
            default => 'CAST(total AS DECIMAL(14,2))',
        };

        $query = $this->baseQuery()
            ->whereBetween('sold_at', [$from, $to])
            ->selectRaw("{$sqlExpression} as {$alias}, SUM({$totalCast}) as revenue")
            ->groupBy(DB::raw($sqlExpression));

        return $query->pluck('revenue', $alias)->all();
    }

    /**
     * @return Builder<Sale>
     */
    private function baseQuery(): Builder
    {
        $query = Sale::query()
            ->where('status', SaleStatus::Completed)
            ->whereNotNull('sold_at');

        $user = Auth::user();

        if ($user instanceof User && ! $user->isAdministrator()) {
            $query->where(function (Builder $builder) use ($user): void {
                $builder->where('created_by', (string) $user->id);

                if (filled($user->email)) {
                    $builder->orWhere('created_by', (string) $user->email);
                }

                if (filled($user->name)) {
                    $builder->orWhere('created_by', (string) $user->name);
                }
            });
        }

        BranchAuthScope::apply($query);

        return $query;
    }

    private function monthPeriodExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', sold_at)",
            'pgsql' => "to_char(sold_at::timestamp, 'YYYY-MM')",
            default => "DATE_FORMAT(sold_at, '%Y-%m')",
        };
    }

    private function dayPeriodExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => 'date(sold_at)',
            'pgsql' => 'sold_at::date',
            default => 'DATE(sold_at)',
        };
    }
}
