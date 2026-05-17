<?php

namespace App\Filament\Widgets;

use App\Enums\SaleStatus;
use App\Filament\Widgets\Support\BrandChartPalette;
use App\Filament\Widgets\Support\IosSalesTrendChartStyle;
use App\Models\Branch;
use App\Models\Sale;
use App\Models\User;
use Carbon\CarbonInterface;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ManagementBranchSalesCurrentMonthDaysChart extends ChartWidget
{
    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.ios-sales-chart';

    protected static ?int $sort = -1;

    protected ?string $heading = 'Ventas por día (mes actual · sucursales visibles)';

    protected ?string $description = 'Comparativo diario del mes actual por sucursal según alcance del usuario (gerencia o administrador).';

    protected ?string $maxHeight = '320px';

    protected string $color = 'success';

    protected function getType(): string
    {
        return 'bar';
    }

    public function getColumnSpan(): int|string|array
    {
        $user = Filament::auth()->user();

        if ($user instanceof User && ($user->isAdministrator() || $user->hasGerenciaRole())) {
            return 'full';
        }

        return 1;
    }

    public function getDescription(): string|Htmlable|null
    {
        $currentMonth = now()->locale('es')->translatedFormat('F Y');

        return ucfirst($currentMonth).' · Total: '.$this->formatUsd($this->totalCurrentMonthAmount());
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        $branchIds = $this->resolvedBranchIdsForCurrentUser();
        $monthStart = now()->startOfMonth();
        $monthEnd = now()->endOfMonth();

        if ($branchIds === []) {
            return [
                'datasets' => [],
                'labels' => $this->dayLabels($monthStart->daysInMonth),
            ];
        }

        $dateExpression = $this->dateGroupExpression();
        $rows = Sale::query()
            ->where('status', SaleStatus::Completed)
            ->whereNotNull('sold_at')
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('sold_at', [$monthStart->copy()->startOfDay(), $monthEnd->copy()->endOfDay()])
            ->selectRaw("branch_id, {$dateExpression} as sold_day, SUM(CAST(total AS DECIMAL(14,2))) as amount")
            ->groupBy('branch_id', DB::raw($dateExpression))
            ->get();

        $branchNames = Branch::query()
            ->whereIn('id', $branchIds)
            ->pluck('name', 'id');

        $indexed = [];
        foreach ($rows as $row) {
            $branchId = (int) $row->branch_id;
            $dayKey = (string) $row->sold_day;
            $indexed[$branchId][$dayKey] = round((float) $row->amount, 2);
        }

        $dayKeys = $this->dayKeys($monthStart);
        $fills = BrandChartPalette::barFills(count($branchIds));
        $hovers = BrandChartPalette::barHovers(count($branchIds));
        $borders = BrandChartPalette::barBorderColors(count($branchIds));

        $datasets = [];
        foreach (array_values($branchIds) as $index => $branchId) {
            $series = [];
            foreach ($dayKeys as $dayKey) {
                $series[] = (float) ($indexed[$branchId][$dayKey] ?? 0.0);
            }

            $branchName = $branchNames[$branchId] ?? ('Sucursal #'.$branchId);
            $datasets[] = [
                'label' => Str::limit((string) $branchName, 28, '…'),
                'data' => $series,
                'backgroundColor' => $fills[$index] ?? 'rgba(16, 185, 129, 0.82)',
                'hoverBackgroundColor' => $hovers[$index] ?? 'rgba(16, 185, 129, 0.96)',
                'borderColor' => $borders[$index] ?? 'rgba(255, 255, 255, 0.28)',
                'hoverBorderColor' => 'rgba(255, 255, 255, 0.55)',
                'borderWidth' => 1,
                'hoverBorderWidth' => 2,
                'borderRadius' => 8,
                'borderSkipped' => false,
            ];
        }

        return [
            'datasets' => $datasets,
            'labels' => $this->dayLabels($monthStart->daysInMonth),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return IosSalesTrendChartStyle::verticalChartOptions();
    }

    /**
     * @return list<int>
     */
    private function resolvedBranchIdsForCurrentUser(): array
    {
        $user = Filament::auth()->user();
        if (! $user instanceof User) {
            return [];
        }

        if ($user->isAdministrator()) {
            return Branch::query()
                ->where('is_active', true)
                ->pluck('id')
                ->map(static fn (mixed $id): int => (int) $id)
                ->unique()
                ->values()
                ->all();
        }

        if (! $user->hasGerenciaRole()) {
            return [];
        }

        $ids = $user->restrictedBranchIdsForQueries();

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): int => (int) $id,
            $ids,
        ))));
    }

    /**
     * @return list<string>
     */
    private function dayLabels(int $daysInMonth): array
    {
        $labels = [];
        for ($day = 1; $day <= $daysInMonth; $day++) {
            $labels[] = str_pad((string) $day, 2, '0', STR_PAD_LEFT);
        }

        return $labels;
    }

    /**
     * @return list<string>
     */
    private function dayKeys(CarbonInterface $monthStart): array
    {
        $keys = [];
        for ($day = 1; $day <= $monthStart->daysInMonth; $day++) {
            $keys[] = $monthStart->copy()->day($day)->format('Y-m-d');
        }

        return $keys;
    }

    private function dateGroupExpression(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => 'date(sold_at)',
            'pgsql' => 'sold_at::date',
            default => 'DATE(sold_at)',
        };
    }

    private function totalCurrentMonthAmount(): float
    {
        $branchIds = $this->resolvedBranchIdsForCurrentUser();
        if ($branchIds === []) {
            return 0.0;
        }

        return round((float) Sale::query()
            ->where('status', SaleStatus::Completed)
            ->whereNotNull('sold_at')
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('sold_at', [now()->startOfMonth(), now()->endOfMonth()])
            ->sum('total'), 2);
    }

    private function formatUsd(float $amount): string
    {
        return '$'.number_format($amount, 2, ',', '.');
    }

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && ($user->hasGerenciaRole() || $user->isAdministrator());
    }
}
