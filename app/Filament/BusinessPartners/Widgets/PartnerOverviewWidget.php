<?php

namespace App\Filament\BusinessPartners\Widgets;

use App\Enums\PartnerPanelPermission;
use App\Filament\BusinessPartners\Pages\AgreementsPage;
use App\Filament\BusinessPartners\Resources\AgreementConsumptions\AgreementConsumptionResource;
use App\Filament\BusinessPartners\Resources\HistoricalOfMovements\HistoricalOfMovementResource;
use App\Filament\BusinessPartners\Resources\Orders\OrderResource;
use App\Filament\BusinessPartners\Widgets\Concerns\ReadsPartnerDashboardMetrics;
use App\Models\PartnerAgreementSale;
use App\Support\Partners\PartnerPanel;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;

/**
 * Cabecera del inicio: saludo, vigencia del convenio, accesos rápidos según permisos, cifras del
 * mes y cupos (crédito y fondo de consumo). Se refresca cada minuto (datos en caché por aliado).
 */
class PartnerOverviewWidget extends Widget
{
    use ReadsPartnerDashboardMetrics;

    protected static bool $isLazy = false;

    protected static bool $isDiscovered = false;

    protected static ?int $sort = 1;

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 'full';

    /**
     * @var view-string
     */
    protected string $view = 'filament.business-partners.widgets.partner-overview';

    public function getPollingInterval(): ?string
    {
        return $this->pollingInterval;
    }

    public static function canView(): bool
    {
        return PartnerPanel::user() !== null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $user = PartnerPanel::user();
        $metrics = $this->metrics();

        return [
            'firstName' => (string) str((string) $user?->name)->before(' '),
            'metrics' => $metrics,
            'showMetrics' => self::canSeeDashboardMetrics() && $metrics !== [],
            'shortcuts' => $this->shortcuts(),
            'agreementsUrl' => PartnerPanel::can(PartnerPanelPermission::PlansAndAgreements) ? AgreementsPage::getUrl() : null,
            'pollingInterval' => $this->getPollingInterval(),
            'usesDefaultPassword' => $user?->usesDefaultPartnerPassword() ?? false,
            'profileUrl' => Filament::getProfileUrl(),
        ];
    }

    /**
     * @return list<array{label: string, description: string, icon: string, url: string, primary: bool}>
     */
    private function shortcuts(): array
    {
        $shortcuts = [];

        if (PartnerPanel::can(PartnerPanelPermission::CreateOrders)) {
            $shortcuts[] = ['label' => 'Crear pedido', 'description' => 'Nuevo pedido para su empresa', 'icon' => 'heroicon-o-plus-circle', 'url' => OrderResource::getUrl('create'), 'primary' => true];
        }

        if (PartnerPanel::can(PartnerPanelPermission::ViewOrders)) {
            $shortcuts[] = ['label' => 'Mis pedidos', 'description' => 'Estado y detalle', 'icon' => 'heroicon-o-clipboard-document-list', 'url' => OrderResource::getUrl('index'), 'primary' => false];
        }

        if (AgreementConsumptionResource::canViewAny() && PartnerAgreementSale::query()->where('partner_company_id', PartnerPanel::companyId())->exists()) {
            $shortcuts[] = ['label' => 'Consumos', 'description' => 'Notas de entrega del convenio', 'icon' => 'heroicon-o-receipt-percent', 'url' => AgreementConsumptionResource::getUrl('index'), 'primary' => false];
        }

        if (HistoricalOfMovementResource::canViewAny()) {
            $shortcuts[] = ['label' => 'Estado de cuenta', 'description' => 'Crédito y movimientos', 'icon' => 'heroicon-o-banknotes', 'url' => HistoricalOfMovementResource::getUrl('index'), 'primary' => false];
        }

        if (PartnerPanel::can(PartnerPanelPermission::PlansAndAgreements)) {
            $shortcuts[] = ['label' => 'Planes y convenios', 'description' => 'Montos y vigencia', 'icon' => 'heroicon-o-shield-check', 'url' => AgreementsPage::getUrl(), 'primary' => false];
        }

        return $shortcuts;
    }
}
