<?php

namespace App\Filament\BusinessPartners\Widgets;

use App\Enums\PartnerPanelPermission;
use App\Filament\BusinessPartners\Widgets\Concerns\ReadsPartnerDashboardMetrics;
use App\Support\Partners\PartnerPanel;
use Filament\Widgets\Widget;

/**
 * Resumen de la línea de crédito (disponible, usado, tope) en «Estado de cuenta».
 */
class PartnerCreditSummaryWidget extends Widget
{
    use ReadsPartnerDashboardMetrics;

    protected static bool $isLazy = false;

    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 'full';

    /**
     * @var view-string
     */
    protected string $view = 'filament.business-partners.widgets.partner-credit-summary';

    public function getPollingInterval(): ?string
    {
        return $this->pollingInterval;
    }

    public static function canView(): bool
    {
        return PartnerPanel::can(PartnerPanelPermission::Account);
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        return [
            'credit' => $this->metrics()['credit'] ?? null,
            'pollingInterval' => $this->getPollingInterval(),
        ];
    }
}
