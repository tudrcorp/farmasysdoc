<?php

namespace App\Filament\Widgets\Concerns;

use App\Support\Filament\DashboardBranchFilter;
use Livewire\Attributes\On;

trait InteractsWithDashboardBranchFilter
{
    #[On('dashboard-branch-filter-changed')]
    public function onDashboardBranchFilterChanged(?int $branchId = null): void
    {
        $this->resetDashboardBranchFilterCaches();

        if (property_exists($this, 'drillDownDate')) {
            $this->drillDownDate = null;
        }

        if (property_exists($this, 'drillDownCategoryId')) {
            $this->drillDownCategoryId = null;
        }

        if (method_exists($this, 'updateChartData')) {
            $this->updateChartData();
        }
    }

    protected function resetDashboardBranchFilterCaches(): void
    {
        if (property_exists($this, 'cachedData')) {
            $this->cachedData = null;
        }

        if (property_exists($this, 'cachedDrillDownPayload')) {
            $this->cachedDrillDownPayload = null;
        }

        if (property_exists($this, 'cachedStats')) {
            $this->cachedStats = null;
        }
    }

    /**
     * @return list<int>
     */
    protected function dashboardBranchIdsForCharts(): array
    {
        return DashboardBranchFilter::resolvedBranchIdsForCharts();
    }

    protected function dashboardBranchFilterSuffix(): string
    {
        if (! DashboardBranchFilter::isFilteredToSingleBranch()) {
            return '';
        }

        $label = DashboardBranchFilter::selectedBranchLabel();

        return filled($label) ? ' · '.$label : '';
    }
}
