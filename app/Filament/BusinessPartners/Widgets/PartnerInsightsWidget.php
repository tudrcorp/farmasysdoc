<?php

namespace App\Filament\BusinessPartners\Widgets;

use App\Enums\PartnerPanelPermission;
use App\Filament\BusinessPartners\Resources\Orders\OrderResource;
use App\Filament\BusinessPartners\Widgets\Concerns\ReadsPartnerDashboardMetrics;
use App\Support\Partners\PartnerPanel;
use Filament\Widgets\Widget;

/**
 * Análisis del inicio: pedidos y monto de los últimos 12 meses, pedidos por estado y cupos
 * (crédito y fondo de consumo). Gráficos en SVG/HTML (sin librería de gráficos): cargan al
 * instante, respetan el tema claro/oscuro y muestran un estado vacío útil sin datos.
 */
class PartnerInsightsWidget extends Widget
{
    use ReadsPartnerDashboardMetrics;

    protected static bool $isLazy = false;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 2;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    /**
     * @var view-string
     */
    protected string $view = 'filament.business-partners.widgets.partner-insights';

    public function getPollingInterval(): ?string
    {
        return $this->pollingInterval;
    }

    public static function canView(): bool
    {
        return self::canSeeDashboardMetrics();
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $metrics = $this->metrics();

        return [
            'series' => $metrics['series'] ?? ['labels' => [], 'orders' => [], 'amounts' => [], 'completed_amounts' => []],
            'statuses' => $metrics['statuses'] ?? [],
            'credit' => $metrics['credit'] ?? null,
            'fund' => $metrics['fund'] ?? null,
            'createOrderUrl' => PartnerPanel::can(PartnerPanelPermission::CreateOrders) ? OrderResource::getUrl('create') : null,
        ];
    }
}
