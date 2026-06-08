<?php

namespace App\Filament\Resources\InventoryStockFailures\Widgets;

use App\Filament\Widgets\Support\BrandChartPalette;
use App\Filament\Widgets\Support\IosSalesTrendChartStyle;
use App\Models\Branch;
use App\Services\Inventory\InventoryStockFailureChartDataService;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

class InventoryStockFailuresByProductChart extends ChartWidget
{
    protected static bool $isDiscovered = false;

    protected static bool $isLazy = false;

    protected static ?int $sort = 0;

    protected int|string|array $columnSpan = 'full';

    protected ?string $maxHeight = '320px';

    protected string $color = 'danger';

    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.inventory-stock-failures-chart';

    public ?int $drillDownBranchId = null;

    /**
     * @var array{labels: list<string>, data: list<int>, branch_ids: list<int>}|null
     */
    private ?array $cachedBranchOverview = null;

    public function mount(): void
    {
        if ($this->filter === null) {
            $this->filter = '';
        }

        parent::mount();
    }

    public function isBranchOverviewMode(): bool
    {
        return $this->drillDownBranchId === null && $this->resolvedBranchIdFromFilter() === null;
    }

    public function drillIntoBranch(int $barIndex): void
    {
        if (! $this->isBranchOverviewMode()) {
            return;
        }

        $payload = $this->branchOverviewPayload();
        $branchIds = $payload['branch_ids'] ?? [];

        if (! isset($branchIds[$barIndex])) {
            return;
        }

        if ((int) ($payload['data'][$barIndex] ?? 0) <= 0) {
            return;
        }

        $this->drillDownBranchId = (int) $branchIds[$barIndex];
        $this->cachedData = null;
        $this->cachedBranchOverview = null;
    }

    public function backToBranchOverview(): void
    {
        $this->drillDownBranchId = null;
        $this->filter = '';
        $this->cachedData = null;
        $this->cachedBranchOverview = null;
    }

    public function updatedFilter(?string $value): void
    {
        $this->drillDownBranchId = null;
        $this->cachedData = null;
        $this->cachedBranchOverview = null;
    }

    public function getHeading(): string|Htmlable|null
    {
        if ($this->isBranchOverviewMode()) {
            return 'Fallas por sucursal';
        }

        $branchName = $this->resolvedBranchName();

        return 'Fallas por producto · '.($branchName ?? 'Sucursal');
    }

    public function getDescription(): string|Htmlable|null
    {
        if ($this->isBranchOverviewMode()) {
            return 'Total de fallas por sucursal activa. Pulsa una barra con fallas para ver los productos afectados.';
        }

        $branchName = $this->resolvedBranchName();

        return 'Productos con más intentos sin existencia en '.($branchName ?? 'la sucursal').'. Cada barra usa un color distinto.';
    }

    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, string>|null
     */
    protected function getFilters(): ?array
    {
        if (! $this->isBranchOverviewMode()) {
            return null;
        }

        return app(InventoryStockFailureChartDataService::class)->branchFilterOptions();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        if ($this->isBranchOverviewMode()) {
            return $this->branchOverviewChartData();
        }

        return $this->productChartData();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        return array_replace_recursive(
            IosSalesTrendChartStyle::verticalChartOptions(),
            [
                'animation' => [
                    'duration' => 560,
                    'easing' => 'easeInOutCubic',
                ],
                'animations' => [
                    'numbers' => [
                        'duration' => 560,
                        'easing' => 'easeInOutCubic',
                    ],
                ],
            ],
        );
    }

    protected function getMaxHeight(): ?string
    {
        if ($this->isBranchOverviewMode()) {
            return $this->maxHeight;
        }

        $productCount = count($this->getCachedData()['labels'] ?? []);
        $height = max(320, min(520, 240 + ($productCount * 18)));

        return $height.'px';
    }

    /**
     * @return array{labels: list<string>, data: list<int>, branch_ids: list<int>}
     */
    public function branchOverviewPayload(): array
    {
        if ($this->cachedBranchOverview !== null) {
            return $this->cachedBranchOverview;
        }

        return $this->cachedBranchOverview = app(InventoryStockFailureChartDataService::class)->failuresByBranch();
    }

    /**
     * @return array<string, mixed>
     */
    private function branchOverviewChartData(): array
    {
        $payload = $this->branchOverviewPayload();
        $count = count($payload['data']);

        if ($count === 0) {
            return [
                'datasets' => [
                    [
                        'label' => 'Fallas por sucursal',
                        'data' => [],
                        'backgroundColor' => [],
                    ],
                ],
                'labels' => [],
            ];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Fallas por sucursal',
                    'data' => $payload['data'],
                    'backgroundColor' => IosSalesTrendChartStyle::vividBarFills($count),
                    'hoverBackgroundColor' => IosSalesTrendChartStyle::vividBarHovers($count),
                    'borderColor' => IosSalesTrendChartStyle::barBorderColors($count),
                    'hoverBorderColor' => 'rgba(255, 255, 255, 0.5)',
                    'borderWidth' => 1,
                    'hoverBorderWidth' => 2,
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                ],
            ],
            'labels' => $payload['labels'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function productChartData(): array
    {
        $branchId = $this->resolvedBranchId();

        if ($branchId === null) {
            return [
                'datasets' => [],
                'labels' => [],
            ];
        }

        $chart = app(InventoryStockFailureChartDataService::class)->failuresByProduct($branchId, 20);
        $count = count($chart['data']);

        if ($count === 0) {
            return [
                'datasets' => [
                    [
                        'label' => 'Fallas por producto',
                        'data' => [],
                        'backgroundColor' => [],
                    ],
                ],
                'labels' => [],
            ];
        }

        return [
            'datasets' => [
                [
                    'label' => 'Fallas por producto',
                    'data' => $chart['data'],
                    'backgroundColor' => BrandChartPalette::barFills($count),
                    'hoverBackgroundColor' => BrandChartPalette::barHovers($count),
                    'borderColor' => BrandChartPalette::barBorderColors($count),
                    'hoverBorderColor' => 'rgba(255, 255, 255, 0.55)',
                    'borderWidth' => 1,
                    'hoverBorderWidth' => 2,
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                ],
            ],
            'labels' => $chart['labels'],
        ];
    }

    private function resolvedBranchId(): ?int
    {
        if ($this->drillDownBranchId !== null) {
            return $this->drillDownBranchId;
        }

        return $this->resolvedBranchIdFromFilter();
    }

    private function resolvedBranchIdFromFilter(): ?int
    {
        $filter = (string) ($this->filter ?? '');

        if ($filter === '' || ! is_numeric($filter)) {
            return null;
        }

        return (int) $filter;
    }

    private function resolvedBranchName(): ?string
    {
        $branchId = $this->resolvedBranchId();

        if ($branchId === null) {
            return null;
        }

        return Branch::query()
            ->whereKey($branchId)
            ->value('name');
    }
}
