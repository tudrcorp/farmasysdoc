<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\ProductBranchSalesRankingService;

class BestSellingProductChart extends AbstractIosProductSalesBarChart
{
    protected static ?int $sort = 2;

    protected ?string $heading = 'Productos más vendidos';

    protected ?string $description = 'Top 20 por número de ventas completadas que incluyen el producto (tu sucursal o todas).';

    protected string $color = 'warning';

    public function getIosShellModifierClass(): string
    {
        return 'fi-farmaadmin-ios-product-chart--best';
    }

    protected function useIntenseWarmBars(): bool
    {
        return true;
    }

    /**
     * @return array{labels: list<string>, data: list<int>}
     */
    protected function loadRanking(): array
    {
        return app(ProductBranchSalesRankingService::class)->topSelling(20);
    }
}
