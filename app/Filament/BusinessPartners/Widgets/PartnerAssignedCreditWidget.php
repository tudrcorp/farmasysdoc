<?php

namespace App\Filament\BusinessPartners\Widgets;

use App\Models\PartnerCompany;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\Widget;
use Illuminate\Support\Number;

/**
 * Muestra cupo asignado, consumido y disponible; se actualiza en intervalos vía Livewire (`wire:poll`).
 * Estilos: theme.css → `.fi-ios-partner-credit-widget`.
 */
class PartnerAssignedCreditWidget extends Widget
{
    protected static ?int $sort = -2;

    protected static bool $isLazy = false;

    protected static bool $isDiscovered = false;

    /**
     * Intervalo de refresco para reflejar consumos al instante (p. ej. al pasar pedidos a «En proceso»).
     */
    protected ?string $pollingInterval = '5s';

    /**
     * @var int | string | array<string, int | null>
     */
    protected int|string|array $columnSpan = 'full';

    /**
     * @var view-string
     */
    protected string $view = 'filament.business-partners.widgets.ios-partner-assigned-credit';

    public static function canView(): bool
    {
        if (! Filament::auth()->check()) {
            return false;
        }

        $user = Filament::auth()->user();

        return $user instanceof User && $user->hasPartnerCompanyAssignedCredit();
    }

    public function getPollingInterval(): ?string
    {
        return $this->pollingInterval;
    }

    /**
     * @return array{
     *     remainingFormatted: string,
     *     limitFormatted: string,
     *     consumedFormatted: string,
     *     partnerLine: string,
     *     pollingInterval: ?string,
     * }
     */
    protected function getViewData(): array
    {
        $user = Filament::auth()->user();
        if (! $user instanceof User || ! $user->isPartnerCompanyUser()) {
            return [
                'remainingFormatted' => '',
                'limitFormatted' => '',
                'consumedFormatted' => '',
                'partnerLine' => '',
                'pollingInterval' => $this->getPollingInterval(),
            ];
        }

        $partner = PartnerCompany::query()->find((int) $user->partner_company_id);
        if ($partner === null) {
            return [
                'remainingFormatted' => '',
                'limitFormatted' => '',
                'consumedFormatted' => '',
                'partnerLine' => '',
                'pollingInterval' => $this->getPollingInterval(),
            ];
        }

        $remaining = $partner->remainingCreditAmount();
        if ($remaining <= 0) {
            return [
                'remainingFormatted' => '',
                'limitFormatted' => '',
                'consumedFormatted' => '',
                'partnerLine' => '',
                'pollingInterval' => $this->getPollingInterval(),
            ];
        }

        $consumed = $partner->totalCreditConsumedAmount();
        $ceiling = $partner->totalCreditCeilingAmount();

        $partnerLine = filled($partner->trade_name)
            ? (string) $partner->trade_name
            : (filled($partner->legal_name) ? (string) $partner->legal_name : '');

        return [
            'remainingFormatted' => Number::currency($remaining, 'USD', 'en', 2),
            'limitFormatted' => Number::currency($ceiling, 'USD', 'en', 2),
            'consumedFormatted' => Number::currency($consumed, 'USD', 'en', 2),
            'partnerLine' => $partnerLine,
            'pollingInterval' => $this->getPollingInterval(),
        ];
    }
}
