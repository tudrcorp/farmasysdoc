<?php

namespace App\Filament\BusinessPartners\Widgets\Concerns;

use App\Enums\PartnerPanelPermission;
use App\Services\Partners\PartnerDashboardMetrics;
use App\Support\Partners\PartnerPanel;

/**
 * Lectura de las métricas del aliado autenticado (cacheadas por aliado en {@see PartnerDashboardMetrics}).
 */
trait ReadsPartnerDashboardMetrics
{
    /**
     * @return array<string, mixed>
     */
    protected function metrics(): array
    {
        $partnerId = PartnerPanel::companyId();

        return $partnerId === null ? [] : app(PartnerDashboardMetrics::class)->for($partnerId);
    }

    protected static function canSeeDashboardMetrics(): bool
    {
        return PartnerPanel::can(PartnerPanelPermission::Dashboard);
    }
}
