<?php

namespace App\Filament\Widgets;

use App\Filament\Widgets\Concerns\InteractsWithDashboardBranchFilter;
use App\Filament\Widgets\Support\BrandChartPalette;
use App\Filament\Widgets\Support\IosProductSalesChartStyle;
use App\Filament\Widgets\Support\IosSalesTrendChartStyle;
use App\Models\User;
use App\Services\Dashboard\CategoryProductQuantitySalesService;
use Carbon\CarbonInterface;
use Filament\Facades\Filament;
use Filament\Widgets\ChartWidget;
use Illuminate\Contracts\Support\Htmlable;

class CategoryProductSalesChart extends ChartWidget
{
    use InteractsWithDashboardBranchFilter;

    private const FILTER_TODAY = 'today';

    private const FILTER_LAST_7_DAYS = 'last_7_days';

    private const FILTER_LAST_30_DAYS = 'last_30_days';

    private const FILTER_CURRENT_MONTH = 'current_month';

    private const FILTER_CURRENT_YEAR = 'current_year';

    private const FILTER_ALL_TIME = 'all_time';

    /**
     * @var view-string
     */
    protected string $view = 'filament.widgets.ios-category-product-sales-chart';

    protected static ?int $sort = 2;

    protected int|string|array $columnSpan = 'full';

    protected ?string $heading = 'Unidades vendidas por categoría';

    protected ?string $maxHeight = '320px';

    protected string $color = 'info';

    public ?int $drillDownCategoryId = null;

    public function mount(): void
    {
        if ($this->filter === null || $this->filter === '') {
            $this->filter = self::FILTER_CURRENT_MONTH;
        }

        parent::mount();
    }

    public function updatedFilter(?string $value): void
    {
        $this->drillDownCategoryId = null;
        $this->cachedData = null;
    }

    public function drillIntoCategory(int $categoryIndex): void
    {
        if (! $this->isCategoryOverviewMode()) {
            return;
        }

        $categoryKeys = $this->categoryKeysForSelectedFilter();
        if (! isset($categoryKeys[$categoryIndex])) {
            return;
        }

        $overview = $this->categoryOverviewPayload();
        $quantity = $overview['data'][$categoryIndex] ?? 0.0;
        if ($quantity <= 0.0) {
            return;
        }

        $this->drillDownCategoryId = $categoryKeys[$categoryIndex];
        $this->cachedData = null;
    }

    public function backToCategoryOverview(): void
    {
        $this->drillDownCategoryId = null;
        $this->cachedData = null;
    }

    public function isCategoryOverviewMode(): bool
    {
        return $this->drillDownCategoryId === null;
    }

    public function getHeading(): string|Htmlable|null
    {
        if (! $this->isCategoryOverviewMode()) {
            $categoryName = $this->drillDownPayload()['category_name'] ?? __('Categoría');

            return __('Productos vendidos · :category', ['category' => $categoryName]);
        }

        return $this->heading;
    }

    public function getDescription(): string|Htmlable|null
    {
        if (! $this->isCategoryOverviewMode()) {
            $payload = $this->drillDownPayload();

            return __('Período: :period · Total en categoría: :total unidades', [
                'period' => $this->selectedFilterLabel(),
                'total' => $this->formatQuantity((float) ($payload['total_quantity'] ?? 0.0)),
            ]).$this->dashboardBranchFilterSuffix();
        }

        $overview = $this->categoryOverviewPayload();

        return __('Período: :period · Total: :total unidades · Pulsa una categoría para ver sus productos', [
            'period' => $this->selectedFilterLabel(),
            'total' => $this->formatQuantity((float) ($overview['total_quantity'] ?? 0.0)),
        ]).$this->dashboardBranchFilterSuffix();
    }

    public static function canView(): bool
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->isAdministrator();
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
        return [
            self::FILTER_TODAY => 'Hoy',
            self::FILTER_LAST_7_DAYS => 'Últimos 7 días',
            self::FILTER_LAST_30_DAYS => 'Últimos 30 días',
            self::FILTER_CURRENT_MONTH => 'Mes actual',
            self::FILTER_CURRENT_YEAR => 'Año actual',
            self::FILTER_ALL_TIME => 'Histórico',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function getData(): array
    {
        if (! $this->isCategoryOverviewMode()) {
            return $this->drillDownChartData();
        }

        return $this->categoryOverviewChartData();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getOptions(): array
    {
        $animation = [
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
        ];

        if (! $this->isCategoryOverviewMode()) {
            return array_replace_recursive(
                IosProductSalesChartStyle::iosChartOptions(true),
                IosProductSalesChartStyle::horizontalScaleConfig(),
                $animation,
            );
        }

        return array_replace_recursive(
            IosSalesTrendChartStyle::verticalChartOptions(),
            $animation,
        );
    }

    protected function getMaxHeight(): ?string
    {
        if (! $this->isCategoryOverviewMode()) {
            $productCount = count($this->getCachedData()['labels'] ?? []);
            $height = max(320, min(520, 220 + ($productCount * 28)));

            return $height.'px';
        }

        return $this->maxHeight;
    }

    /**
     * @return array<string, mixed>
     */
    private function categoryOverviewChartData(): array
    {
        $overview = $this->categoryOverviewPayload();
        $count = count($overview['data']);

        if ($count === 0) {
            return [
                'datasets' => [
                    [
                        'label' => $this->datasetLabelForSelectedFilter(),
                        'data' => [],
                        'backgroundColor' => [],
                    ],
                ],
                'labels' => [],
            ];
        }

        $fills = IosSalesTrendChartStyle::vividBarFills($count);
        $hovers = IosSalesTrendChartStyle::vividBarHovers($count);
        $borders = IosSalesTrendChartStyle::vividBarBorderColors($count);

        return [
            'datasets' => [
                [
                    'label' => $this->datasetLabelForSelectedFilter(),
                    'data' => $overview['data'],
                    'backgroundColor' => $fills,
                    'hoverBackgroundColor' => $hovers,
                    'borderColor' => $borders,
                    'hoverBorderColor' => $borders,
                    'borderWidth' => 2,
                    'hoverBorderWidth' => 2,
                    'borderRadius' => 8,
                    'borderSkipped' => false,
                ],
            ],
            'labels' => $overview['labels'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function drillDownChartData(): array
    {
        $payload = $this->drillDownPayload();
        $count = count($payload['data']);

        if ($count === 0) {
            return [
                'datasets' => [
                    [
                        'label' => __('Unidades vendidas'),
                        'data' => [],
                        'backgroundColor' => [],
                    ],
                ],
                'labels' => [],
            ];
        }

        $fills = BrandChartPalette::seriesBarFills($count);
        $hovers = BrandChartPalette::seriesBarHovers($count);
        $borders = BrandChartPalette::seriesBarBorderColors($count);

        return [
            'datasets' => [
                [
                    'label' => __('Unidades vendidas'),
                    'data' => $payload['data'],
                    'backgroundColor' => $fills,
                    'hoverBackgroundColor' => $hovers,
                    'borderColor' => $borders,
                    'hoverBorderColor' => $borders,
                    'borderWidth' => 2,
                    'hoverBorderWidth' => 2,
                    'borderRadius' => 7,
                    'borderSkipped' => false,
                ],
            ],
            'labels' => $payload['labels'],
        ];
    }

    /**
     * @return array{
     *     labels: list<string>,
     *     data: list<float>,
     *     category_ids: list<int>,
     *     total_quantity: float,
     * }
     */
    private function categoryOverviewPayload(): array
    {
        [$from, $to] = $this->resolveDateRangeForFilter();

        return app(CategoryProductQuantitySalesService::class)->totalsByCategory($from, $to);
    }

    /**
     * @return array{
     *     labels: list<string>,
     *     data: list<float>,
     *     category_name: string,
     *     total_quantity: float,
     * }
     */
    private function drillDownPayload(): array
    {
        if ($this->drillDownCategoryId === null) {
            return [
                'labels' => [],
                'data' => [],
                'category_name' => '',
                'total_quantity' => 0.0,
            ];
        }

        [$from, $to] = $this->resolveDateRangeForFilter();

        return app(CategoryProductQuantitySalesService::class)
            ->totalsByProductInCategory($this->drillDownCategoryId, $from, $to);
    }

    /**
     * @return list<int>
     */
    private function categoryKeysForSelectedFilter(): array
    {
        return $this->categoryOverviewPayload()['category_ids'];
    }

    /**
     * @return array{0: CarbonInterface|null, 1: CarbonInterface|null}
     */
    private function resolveDateRangeForFilter(): array
    {
        $now = now();

        return match ($this->resolvedFilter()) {
            self::FILTER_TODAY => [$now->copy()->startOfDay(), $now->copy()->endOfDay()],
            self::FILTER_LAST_7_DAYS => [$now->copy()->subDays(6)->startOfDay(), $now->copy()->endOfDay()],
            self::FILTER_LAST_30_DAYS => [$now->copy()->subDays(29)->startOfDay(), $now->copy()->endOfDay()],
            self::FILTER_CURRENT_MONTH => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
            self::FILTER_CURRENT_YEAR => [$now->copy()->startOfYear(), $now->copy()->endOfDay()],
            self::FILTER_ALL_TIME => [null, null],
            default => [$now->copy()->startOfMonth(), $now->copy()->endOfDay()],
        };
    }

    private function resolvedFilter(): string
    {
        $filter = (string) ($this->filter ?? self::FILTER_CURRENT_MONTH);

        return in_array($filter, [
            self::FILTER_TODAY,
            self::FILTER_LAST_7_DAYS,
            self::FILTER_LAST_30_DAYS,
            self::FILTER_CURRENT_MONTH,
            self::FILTER_CURRENT_YEAR,
            self::FILTER_ALL_TIME,
        ], true) ? $filter : self::FILTER_CURRENT_MONTH;
    }

    private function datasetLabelForSelectedFilter(): string
    {
        return match ($this->resolvedFilter()) {
            self::FILTER_TODAY => __('Unidades vendidas por categoría (hoy)'),
            self::FILTER_LAST_7_DAYS => __('Unidades vendidas por categoría (últimos 7 días)'),
            self::FILTER_LAST_30_DAYS => __('Unidades vendidas por categoría (últimos 30 días)'),
            self::FILTER_CURRENT_MONTH => __('Unidades vendidas por categoría (mes actual)'),
            self::FILTER_CURRENT_YEAR => __('Unidades vendidas por categoría (año actual)'),
            self::FILTER_ALL_TIME => __('Unidades vendidas por categoría (histórico)'),
            default => __('Unidades vendidas por categoría'),
        };
    }

    private function selectedFilterLabel(): string
    {
        return match ($this->resolvedFilter()) {
            self::FILTER_TODAY => 'Hoy',
            self::FILTER_LAST_7_DAYS => 'Últimos 7 días',
            self::FILTER_LAST_30_DAYS => 'Últimos 30 días',
            self::FILTER_CURRENT_MONTH => 'Mes actual',
            self::FILTER_CURRENT_YEAR => 'Año actual',
            self::FILTER_ALL_TIME => 'Histórico',
            default => 'Mes actual',
        };
    }

    private function formatQuantity(float $quantity): string
    {
        return rtrim(rtrim(number_format($quantity, 3, ',', '.'), '0'), ',');
    }
}
