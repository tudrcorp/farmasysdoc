<?php

namespace App\Filament\BusinessPartners\Widgets;

use App\Enums\PartnerPanelPermission;
use App\Filament\BusinessPartners\Resources\AgreementConsumptions\AgreementConsumptionResource;
use App\Filament\BusinessPartners\Resources\Orders\OrderResource;
use App\Filament\BusinessPartners\Widgets\Concerns\ReadsPartnerDashboardMetrics;
use App\Services\Partners\PartnerDashboardMetrics;
use App\Support\Partners\PartnerPanel;
use Filament\Widgets\Widget;

/**
 * Productos más solicitados (90 días) y últimos movimientos (pedidos y consumos por convenio),
 * con enlace al detalle según los permisos.
 */
class PartnerActivityWidget extends Widget
{
    use ReadsPartnerDashboardMetrics;

    protected static bool $isLazy = false;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 3;

    protected ?string $pollingInterval = null;

    protected int|string|array $columnSpan = 'full';

    /**
     * @var view-string
     */
    protected string $view = 'filament.business-partners.widgets.partner-activity';

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
        $canViewOrders = PartnerPanel::can(PartnerPanelPermission::ViewOrders);
        $canViewConsumptions = AgreementConsumptionResource::canViewAny();

        return [
            'topProducts' => $metrics['top_products'] ?? [],
            'days' => PartnerDashboardMetrics::TOP_PRODUCTS_DAYS,
            'recentOrders' => array_map(fn (array $item): array => [
                ...$item,
                'url' => match ($item['type'] ?? 'order') {
                    'consumption' => $canViewConsumptions ? AgreementConsumptionResource::getUrl('view', ['record' => $item['id']]) : null,
                    default => $canViewOrders ? OrderResource::getUrl('view', ['record' => $item['id']]) : null,
                },
            ], $metrics['recent_activity'] ?? []),
            'ordersUrl' => $canViewOrders ? OrderResource::getUrl('index') : null,
            'consumptionsUrl' => $canViewConsumptions ? AgreementConsumptionResource::getUrl('index') : null,
        ];
    }
}
