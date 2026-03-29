<?php

namespace App\Filament\Widgets;

use App\Services\Dashboard\ProductBranchSalesRankingService;

class LeastSellingProductChart extends AbstractIosProductSalesBarChart
{
    protected static ?int $sort = 3;

    protected ?string $heading = 'Productos menos vendidos';

    protected ?string $description = 'Top 20 con menos ventas completadas que incluyen el producto (tu sucursal o todas).';

    protected string $color = 'gray';

    public function getIosShellModifierClass(): string
    {
        return 'fi-farmaadmin-ios-product-chart--least';
    }

    protected function useIntenseWarmBars(): bool
    {
        return false;
    }

    /**
     * @return array{labels: list<string>, data: list<int>}
     */
    protected function loadRanking(): array
    {
        return app(ProductBranchSalesRankingService::class)->leastSelling(20);
    }
}
