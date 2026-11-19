<?php

namespace App\Services\Partners;

use App\Enums\PlanStatus;
use App\Models\PartnerCompanyPlan;
use App\Models\Plan;
use App\Models\PlanAffiliateBand;
use App\Models\PlanAgeRange;
use App\Models\PlanSubplan;
use App\Models\User;
use App\Support\Plans\PlanPremiumConverter;
use Illuminate\Support\Facades\DB;

/**
 * Operaciones de mantenimiento de planes: coherencia de la tarifa, duplicado y
 * actualización de primas en bolívares con la tasa BCV.
 */
class PlanMaintenance
{
    public function __construct(private PlanCostStructureSynchronizer $costStructure) {}

    /**
     * Deja solo los datos de la modalidad de tarifa elegida: única (sin rangos),
     * por rangos (sin prima única), retail (sin prima ni rangos) o fondo de consumo
     * (sin prima ni rangos; el monto y los porcentajes viven en cada asignación a aliado) o por
     * cantidad de afiliados (sin prima ni rangos; el baremo vive en tramos y subplanes).
     */
    public function normalizePremiumMode(Plan $plan): void
    {
        if (! $plan->usesAffiliateBands()) {
            $plan->partnerAssignments()->get()->each(fn (PartnerCompanyPlan $assignment) => $assignment->update([
                'plan_subplan_id' => null,
                'affiliates_count' => null,
                'agreed_rate_usd' => null,
                'agreed_band_label' => null,
            ]));
            $plan->affiliateBands()->get()->each(fn (PlanAffiliateBand $band) => $band->delete());
            $plan->subplans()->get()->each(fn (PlanSubplan $subplan) => $subplan->delete());

            if ($plan->has_subplans) {
                $plan->forceFill(['has_subplans' => false])->save();
            }

            if ($plan->prices_medicines_by_cost_profit || $plan->medicine_cost_profit_percent !== null) {
                $plan->forceFill([
                    'prices_medicines_by_cost_profit' => false,
                    'medicine_cost_profit_percent' => null,
                ])->save();
            }
        }

        if (! $plan->usesFlatPremium() && ($plan->monthly_premium_usd !== null || $plan->monthly_premium_ves !== null)) {
            $plan->forceFill(['monthly_premium_usd' => null, 'monthly_premium_ves' => null])->save();
        }

        if (! $plan->usesAgeRanges()) {
            $plan->ageRanges()->get()->each(fn (PlanAgeRange $range) => $range->delete());
        }

        if (! $plan->isConsumptionFund()) {
            $plan->partnerAssignments()->get()->each(fn (PartnerCompanyPlan $assignment) => $assignment->update([
                'authorized_amount_usd' => null,
                'authorized_amount_ves' => null,
                'applies_intermediary_profit' => false,
                'applies_partner_profit' => false,
            ]));
        }
    }

    /**
     * Copia el plan con servicios, rangos, catálogos, subplanes y baremo; queda en borrador y sin aliados.
     */
    public function duplicate(Plan $source, string $code, string $name, ?User $user): Plan
    {
        return DB::transaction(function () use ($source, $code, $name, $user): Plan {
            $copy = $source->replicate(['code', 'name', 'status', 'created_by', 'updated_by']);
            $copy->fill([
                'code' => $code,
                'name' => $name,
                'status' => PlanStatus::Draft,
                'created_by' => $user?->getKey(),
                'updated_by' => $user?->getKey(),
            ]);
            $copy->save();

            $copy->services()->sync($source->services()->pluck('plan_services.id')->all());
            $copy->formularies()->sync($source->formularies()->pluck('formularies.id')->all());

            $source->ageRanges()->get()->each(fn (PlanAgeRange $range) => $copy->ageRanges()->create(
                $range->only(['min_age', 'max_age', 'monthly_premium_usd', 'monthly_premium_ves', 'sort'])
            ));

            $this->costStructure->copy($source, $copy);

            return $copy;
        });
    }

    /**
     * Recalcula en bolívares las primas (única y por rango) y los montos de consumo
     * autorizado de cada aliado (fondo de consumo) con la tasa indicada.
     *
     * @return int Cantidad de montos actualizados.
     */
    public function refreshVesPremiums(Plan $plan, float $rate, ?User $user): int
    {
        return DB::transaction(function () use ($plan, $rate, $user): int {
            $updated = 0;

            if ($plan->usesFlatPremium() && $plan->monthly_premium_usd !== null) {
                $plan->monthly_premium_ves = PlanPremiumConverter::usdToVes($plan->monthly_premium_usd, $rate);
                $updated++;
            }

            $plan->updated_by = $user?->getKey();
            $plan->save();

            if ($plan->usesAgeRanges()) {
                $plan->ageRanges()->get()->each(function (PlanAgeRange $range) use ($rate, &$updated): void {
                    $range->update(['monthly_premium_ves' => PlanPremiumConverter::usdToVes($range->monthly_premium_usd, $rate)]);
                    $updated++;
                });
            }

            if ($plan->isConsumptionFund()) {
                $plan->partnerAssignments()->whereNotNull('authorized_amount_usd')->get()
                    ->each(function (PartnerCompanyPlan $assignment) use ($rate, $user, &$updated): void {
                        $assignment->update([
                            'authorized_amount_ves' => PlanPremiumConverter::usdToVes($assignment->authorized_amount_usd, $rate),
                            'updated_by' => $user?->getKey(),
                        ]);
                        $updated++;
                    });
            }

            return $updated;
        });
    }
}
