<?php

namespace App\Filament\Resources\PartnerAgreements\Widgets;

use App\Enums\PartnerAgreementStatus;
use App\Filament\Resources\PartnerAgreements\PartnerCompanyAgreementResource;
use App\Models\PartnerCompanyAgreement;
use Carbon\CarbonImmutable;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Widget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resumen accionable de convenios: cada tarjeta muestra el contador, un dato de contexto y abre
 * la pestaña correspondiente de la tabla.
 */
class PartnerAgreementsOverview extends Widget
{
    protected static bool $isDiscovered = false;

    /**
     * @var view-string
     */
    protected string $view = 'filament.partner-agreements.overview';

    protected int|string|array $columnSpan = 'full';

    /**
     * @return array<string, mixed>
     */
    protected function getViewData(): array
    {
        $today = CarbonImmutable::today();

        return [
            'cards' => [
                $this->activeCard(),
                $this->expiringCard($today),
                $this->pendingCard($today),
                $this->expiredCard(),
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function activeCard(): array
    {
        $active = $this->query()->active();
        $count = (clone $active)->count();
        $autoRenewing = (clone $active)->where('auto_renews', true)->count();

        return $this->card(
            label: 'Vigentes',
            count: $count,
            tone: 'success',
            icon: Heroicon::CheckBadge,
            tab: 'active',
            detail: $count > 0
                ? $autoRenewing.' con renovación automática'
                : 'Aún no hay convenios vigentes',
            progress: $count > 0 ? [['value' => $autoRenewing, 'tone' => 'success'], ['value' => $count - $autoRenewing, 'tone' => 'track']] : [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function expiringCard(CarbonImmutable $today): array
    {
        $expiring = $this->query()->expiringWithin(30)->with('partnerCompany:id,legal_name')->orderBy('ends_on')->get();
        $next = $expiring->first();

        $buckets = [
            ['label' => '≤ 7 d', 'value' => $expiring->filter(fn (PartnerCompanyAgreement $a): bool => $a->daysUntilEnd() <= 7)->count(), 'tone' => 'danger'],
            ['label' => '8–15 d', 'value' => $expiring->filter(fn (PartnerCompanyAgreement $a): bool => $a->daysUntilEnd() > 7 && $a->daysUntilEnd() <= 15)->count(), 'tone' => 'warning'],
            ['label' => '16–30 d', 'value' => $expiring->filter(fn (PartnerCompanyAgreement $a): bool => $a->daysUntilEnd() > 15)->count(), 'tone' => 'info'],
        ];

        return $this->card(
            label: 'Vencen en 30 días',
            count: $expiring->count(),
            tone: 'warning',
            icon: Heroicon::CalendarDays,
            tab: 'expiring',
            detail: $next instanceof PartnerCompanyAgreement
                ? 'Próximo: '.$next->partnerCompany?->legal_name
                : 'Sin vencimientos próximos',
            chip: $next instanceof PartnerCompanyAgreement ? self::daysLabel($next->daysUntilEnd()).' · '.$next->ends_on->format('d/m') : null,
            progress: $expiring->isNotEmpty() ? $buckets : [],
            legend: $expiring->isNotEmpty() ? $buckets : [],
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function pendingCard(CarbonImmutable $today): array
    {
        $pending = $this->query()->where('status', PartnerAgreementStatus::PendingApproval->value);
        $count = (clone $pending)->count();
        $oldest = (clone $pending)->whereNotNull('submitted_at')->oldest('submitted_at')->value('submitted_at');
        $waitingDays = $oldest !== null ? (int) CarbonImmutable::parse($oldest)->startOfDay()->diffInDays($today) : null;

        return $this->card(
            label: 'Por aprobar',
            count: $count,
            tone: 'info',
            icon: Heroicon::Clock,
            tab: 'pending',
            detail: $count > 0 ? 'Requiere aprobación de un Administrador' : 'Nada pendiente de aprobación',
            chip: $waitingDays !== null ? ($waitingDays === 0 ? 'Enviado hoy' : 'El más antiguo espera '.self::daysLabel($waitingDays, past: true)) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function expiredCard(): array
    {
        $expired = $this->query()
            ->where('status', PartnerAgreementStatus::Expired->value)
            ->whereNotExists(function ($sub): void {
                $sub->selectRaw('1')
                    ->from('partner_company_agreements as current')
                    ->whereColumn('current.partner_company_id', 'partner_company_agreements.partner_company_id')
                    ->whereIn('current.status', [PartnerAgreementStatus::Active->value, PartnerAgreementStatus::Approved->value]);
            })
            ->with('partnerCompany:id,legal_name')
            ->orderByDesc('ends_on')
            ->get()
            ->unique('partner_company_id');

        $names = $expired->take(2)->map(fn (PartnerCompanyAgreement $a): string => (string) $a->partnerCompany?->legal_name)->implode(', ');
        $extra = max(0, $expired->count() - 2);

        return $this->card(
            label: 'Aliados sin convenio',
            count: $expired->count(),
            tone: 'danger',
            icon: Heroicon::ShieldExclamation,
            tab: 'expired',
            detail: $expired->isNotEmpty()
                ? $names.($extra > 0 ? " y {$extra} más" : '')
                : 'Todos los convenios vencidos fueron renovados',
            chip: $expired->isNotEmpty() ? 'Vencidos sin renovar' : null,
        );
    }

    /**
     * @param  list<array{value: int, tone: string, label?: string}>  $progress
     * @param  list<array{value: int, tone: string, label: string}>  $legend
     * @return array<string, mixed>
     */
    private function card(
        string $label,
        int $count,
        string $tone,
        Heroicon $icon,
        string $tab,
        string $detail,
        ?string $chip = null,
        array $progress = [],
        array $legend = [],
    ): array {
        $total = array_sum(array_column($progress, 'value'));

        return [
            'label' => $label,
            'count' => $count,
            'icon' => $icon,
            'url' => PartnerCompanyAgreementResource::getUrl('index', ['tab' => $tab]),
            'detail' => $detail,
            'chip' => $chip,
            'tone' => $count > 0 ? $tone : 'muted',
            'isEmpty' => $count === 0,
            'progress' => $total > 0
                ? array_map(fn (array $segment): array => [...$segment, 'width' => round($segment['value'] / $total * 100, 2)], array_values(array_filter($progress, fn (array $segment): bool => $segment['value'] > 0)))
                : [],
            'legend' => $legend,
        ];
    }

    /**
     * @return Builder<PartnerCompanyAgreement>
     */
    private function query(): Builder
    {
        return PartnerCompanyAgreementResource::getEloquentQuery();
    }

    private static function daysLabel(int $days, bool $past = false): string
    {
        if ($days <= 0) {
            return $past ? 'hoy' : 'vence hoy';
        }

        $unit = $days === 1 ? 'día' : 'días';

        return $past ? "{$days} {$unit}" : "en {$days} {$unit}";
    }
}
