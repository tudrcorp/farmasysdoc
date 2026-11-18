<?php

namespace App\Filament\BusinessPartners\Widgets;

use App\Enums\PartnerFundMovementType;
use App\Enums\PartnerPanelPermission;
use App\Models\PartnerCompanyPlan;
use App\Models\PartnerFundMovement;
use App\Services\Partners\PartnerAffiliateService;
use App\Support\Partners\PartnerPanel;
use Filament\Widgets\Widget;

/**
 * Saldo de cada fondo de consumo (plan Flexible) del aliado y sus últimos movimientos.
 */
class PartnerFundSummaryWidget extends Widget
{
    protected static bool $isLazy = false;

    protected static bool $isDiscovered = false;

    protected ?string $pollingInterval = '60s';

    protected int|string|array $columnSpan = 'full';

    /**
     * @var view-string
     */
    protected string $view = 'filament.business-partners.widgets.partner-fund-summary';

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
        $partnerId = PartnerPanel::companyId();

        $funds = PartnerAffiliateService::flexibleAssignmentsQuery()
            ->where('partner_company_id', $partnerId)
            ->with('plan:id,name')
            ->withCount(['affiliates as active_affiliates_count' => fn ($query) => $query->where('is_active', true)])
            ->get()
            ->map(function (PartnerCompanyPlan $assignment): array {
                $totals = $assignment->fundMovements()
                    ->selectRaw('type, SUM(amount_usd) AS total')
                    ->groupBy('type')
                    ->pluck('total', 'type');

                $authorized = (float) ($assignment->authorized_amount_usd ?? 0);
                $recharges = (float) ($totals[PartnerFundMovementType::Recharge->value] ?? 0);
                $consumed = abs((float) ($totals[PartnerFundMovementType::Consumption->value] ?? 0)) - (float) ($totals[PartnerFundMovementType::Reversal->value] ?? 0);
                $total = $authorized + $recharges;
                $available = round($total - $consumed, 2);

                return [
                    'plan' => (string) $assignment->plan?->name,
                    'authorized' => $authorized,
                    'recharges' => $recharges,
                    'consumed' => round($consumed, 2),
                    'available' => $available,
                    'percent' => $total > 0 ? round(min(100, max(0, $consumed / $total * 100)), 2) : 0.0,
                    'affiliates' => (int) $assignment->active_affiliates_count,
                ];
            })
            ->all();

        $movements = PartnerFundMovement::query()
            ->where('partner_company_id', $partnerId)
            ->latest('id')
            ->limit(8)
            ->get()
            ->map(fn (PartnerFundMovement $movement): array => [
                'date' => $movement->created_at?->format('d/m/Y h:i a'),
                'type' => $movement->type->label(),
                'color' => $movement->type->color(),
                'description' => $movement->description,
                'amount' => (float) $movement->amount_usd,
                'balance' => (float) $movement->balance_after_usd,
            ])
            ->all();

        return [
            'funds' => $funds,
            'movements' => $movements,
            'pollingInterval' => $this->getPollingInterval(),
        ];
    }
}
