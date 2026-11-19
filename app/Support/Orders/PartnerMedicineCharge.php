<?php

namespace App\Support\Orders;

use App\Enums\PlanStatus;
use App\Models\Order;
use App\Models\PartnerCompany;
use App\Models\PartnerCompanyPlan;
use App\Models\Plan;

/**
 * Cómo se cobra cada medicamento a un aliado según el plan activo que tenga asignado.
 */
final class PartnerMedicineCharge
{
    public function __construct(
        public bool $usesCostProfit,
        public float $profitPercent,
        public ?string $planName,
        public ?string $bandLabel,
        public ?int $affiliatesCount,
    ) {}

    public static function forCompany(?PartnerCompany $company): self
    {
        if (! $company instanceof PartnerCompany) {
            return new self(false, 0.0, null, null, null);
        }

        $assignment = PartnerCompanyPlan::query()
            ->where('partner_company_id', $company->getKey())
            ->whereHas('plan', function ($query): void {
                $query->where('status', PlanStatus::Active->value)
                    ->where('prices_medicines_by_cost_profit', true);
            })
            ->orderByDesc('id')
            ->first();

        if ($assignment instanceof PartnerCompanyPlan && is_numeric($assignment->affiliates_count)) {
            $plan = $assignment->plan()->with('affiliateBands.prices')->first();
            $subplan = $plan instanceof Plan && $plan->usesSubplans() ? $assignment->subplan : null;
            $rate = $plan?->affiliateRateFor((int) $assignment->affiliates_count, $subplan);

            if ($plan instanceof Plan && $rate !== null) {
                return new self(
                    true,
                    max(0.0, (float) $rate['rate_usd']),
                    (string) $plan->name,
                    $rate['band']->label(),
                    (int) $assignment->affiliates_count,
                );
            }
        }

        return new self(
            false,
            max(0.0, (float) ($company->profit_percentage_a ?? 0)),
            null,
            null,
            null,
        );
    }

    public function rememberOn(Order $order): void
    {
        $order->forceFill([
            'partner_prices_by_cost_profit' => $this->usesCostProfit,
            'partner_pricing_plan_name' => $this->usesCostProfit ? $this->planName : null,
            'partner_pricing_band_label' => $this->usesCostProfit ? $this->bandLabel : null,
            'partner_pricing_profit_percent' => $this->usesCostProfit ? $this->profitPercent : null,
            'partner_pricing_affiliates_count' => $this->usesCostProfit ? $this->affiliatesCount : null,
        ])->saveQuietly();
    }
}
