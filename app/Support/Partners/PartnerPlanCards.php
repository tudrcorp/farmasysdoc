<?php

namespace App\Support\Partners;

use App\Filament\Resources\Plans\PlanResource;
use App\Models\PartnerCompany;
use App\Models\PartnerCompanyPlan;
use App\Models\Plan;

/**
 * Datos de las tarjetas de planes de un aliado (vista `filament.partner-companies.plan-cards`).
 * Las usan la ficha del aliado en Farmaadmin y la página «Planes» de dale · Aliados.
 */
final class PartnerPlanCards
{
    /**
     * Datos de las tarjetas de planes del aliado (ya formateados para la vista).
     *
     * @return list<array<string, mixed>>
     */
    public static function for(PartnerCompany $partner, bool $linkToPlans = false): array
    {
        $money = fn (mixed $value): string => '$'.number_format((float) $value, 2, ',', '.');

        $plans = $partner->plans()
            ->with(['services', 'formularies', 'affiliateBands.prices', 'ageRanges'])
            ->orderBy('name')
            ->get();

        return $plans->map(function (Plan $plan) use ($money, $linkToPlans): array {
            $assignment = $plan->pivot instanceof PartnerCompanyPlan
                ? $plan->pivot->loadMissing(['subplan.services', 'subplan.formularies'])
                : null;
            $subplan = $assignment?->subplan?->setRelation('plan', $plan);

            $metrics = [];

            if ($plan->usesAffiliateBands()) {
                if ($plan->usesSubplans()) {
                    $metrics[] = ['label' => 'Subplan', 'value' => $subplan?->name ?? 'Sin elegir'];
                }

                $metrics[] = [
                    'label' => 'Afiliados',
                    'value' => $assignment?->affiliates_count === null ? 'Sin indicar' : number_format($assignment->affiliates_count, 0, ',', '.'),
                    'hint' => filled($assignment?->agreed_band_label) ? 'Tramo '.$assignment->agreed_band_label : null,
                ];
                $metrics[] = [
                    'label' => 'Tarifa por afiliado',
                    'value' => $assignment?->agreed_rate_usd === null ? '—' : $money($assignment->agreed_rate_usd),
                    'hint' => 'USD al mes',
                ];

                $total = $assignment?->monthlyTotalUsd();
                $highlight = ['label' => 'Total mensual', 'value' => $total === null ? '—' : $money($total), 'hint' => 'USD · pago anticipado'];
            } elseif ($plan->isConsumptionFund()) {
                $metrics[] = [
                    'label' => 'En bolívares',
                    'value' => $assignment?->authorized_amount_ves === null ? '—' : 'Bs. '.number_format((float) $assignment->authorized_amount_ves, 2, ',', '.'),
                ];
                $metrics[] = ['label' => 'Ganancias', 'value' => $assignment?->profitSharingLabel() ?? 'No aplican'];

                $highlight = [
                    'label' => 'Consumo autorizado',
                    'value' => $assignment?->authorized_amount_usd === null ? 'Sin monto' : $money($assignment->authorized_amount_usd),
                    'hint' => 'USD',
                ];
            } else {
                $highlight = ['label' => 'Prima mensual por persona', 'value' => $plan->premiumSummary(), 'hint' => null];
            }

            return [
                'name' => $plan->name,
                'code' => $plan->code,
                'url' => $linkToPlans ? PlanResource::getUrl('view', ['record' => $plan]) : null,
                'status' => ['label' => $plan->status->label(), 'color' => $plan->status->color()],
                'mode' => $plan->premium_mode->label(),
                'metrics' => $metrics,
                'highlight' => $highlight,
                'services' => ($subplan?->effectiveServices() ?? $plan->services)->pluck('name')->all(),
                'formularies' => ($subplan?->effectiveFormularies() ?? $plan->formularies)->pluck('name')->all(),
                'inherits' => $subplan !== null,
            ];
        })->values()->all();
    }
}
