<?php

namespace App\Filament\BusinessPartners\Widgets;

use App\Enums\OrderStatus;
use App\Filament\BusinessPartners\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Collection;

/**
 * Conteo de pedidos del aliado por estado, estilo glass iOS alineado a Farmaadmin (`theme.css` → `.fi-business-partners-stats-order-ios`).
 */
class StatsSatatusOrderOverview extends StatsOverviewWidget
{
    /**
     * @var view-string
     */
    protected string $view = 'filament.business-partners.widgets.stats-status-order-overview';

    protected static ?int $sort = -1;

    protected static bool $isDiscovered = false;

    /**
     * @var int|array<string, ?int>|null
     */
    protected int|array|null $columns = ['@sm' => 1, '@md' => 3];

    protected ?string $heading = 'Pedidos por estado';

    protected ?string $description = 'Conteo en tiempo real de los pedidos de su compañía';

    protected ?string $pollingInterval = '5s';

    public static function canView(): bool
    {
        if (! Filament::auth()->check()) {
            return false;
        }

        $user = Filament::auth()->user();

        return $user instanceof User && $user->isPartnerCompanyUser();
    }

    /**
     * @return array<string, int>
     */
    private static function countsByStatusForPartner(int $partnerCompanyId): array
    {
        /** @var Collection<string, int> $rows */
        $rows = Order::query()
            ->where('partner_company_id', $partnerCompanyId)
            ->selectRaw('status, COUNT(*) as aggregate')
            ->groupBy('status')
            ->pluck('aggregate', 'status');

        return [
            OrderStatus::Pending->value => (int) ($rows[OrderStatus::Pending->value] ?? 0),
            OrderStatus::InProgress->value => (int) ($rows[OrderStatus::InProgress->value] ?? 0),
            OrderStatus::Completed->value => (int) ($rows[OrderStatus::Completed->value] ?? 0),
        ];
    }

    /**
     * @return array<Stat>
     */
    protected function getStats(): array
    {
        $user = Filament::auth()->user();
        if (! $user instanceof User || ! $user->isPartnerCompanyUser()) {
            return [];
        }

        $counts = self::countsByStatusForPartner((int) $user->partner_company_id);

        $ordersIndexUrl = OrderResource::getUrl('index');

        return [
            Stat::make(OrderStatus::Pending->label(), (string) $counts[OrderStatus::Pending->value])
                ->description('Aún sin iniciar en operación')
                ->descriptionColor('danger')
                ->color('danger')
                ->icon(Heroicon::Clock)
                ->url($ordersIndexUrl)
                ->extraAttributes([
                    'class' => 'fi-bp-ios-order-stat fi-bp-ios-order-stat--pending',
                ]),
            Stat::make(OrderStatus::InProgress->label(), (string) $counts[OrderStatus::InProgress->value])
                ->description('En curso (p. ej. entrega)')
                ->descriptionColor('warning')
                ->color('warning')
                ->icon(Heroicon::ArrowPath)
                ->url($ordersIndexUrl)
                ->extraAttributes([
                    'class' => 'fi-bp-ios-order-stat fi-bp-ios-order-stat--progress',
                ]),
            Stat::make(OrderStatus::Completed->label(), (string) $counts[OrderStatus::Completed->value])
                ->description('Finalizados')
                ->descriptionColor('success')
                ->color('success')
                ->icon(Heroicon::CheckCircle)
                ->url($ordersIndexUrl)
                ->extraAttributes([
                    'class' => 'fi-bp-ios-order-stat fi-bp-ios-order-stat--completed',
                ]),
        ];
    }
}
