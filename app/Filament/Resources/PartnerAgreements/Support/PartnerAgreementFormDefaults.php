<?php

namespace App\Filament\Resources\PartnerAgreements\Support;

use App\Enums\PartnerAgreementType;
use App\Models\PartnerCompany;
use App\Services\Partners\PartnerAgreementRules;
use Carbon\CarbonImmutable;

/**
 * Valores sugeridos al elegir aliado y tipo: condiciones actuales del aliado y fechas coherentes
 * con el convenio anterior (la renovación arranca el día siguiente al fin; la modificación hoy).
 */
final class PartnerAgreementFormDefaults
{
    /**
     * @return array<string, mixed>
     */
    public static function for(?int $partnerId, ?PartnerAgreementType $type): array
    {
        $partner = $partnerId !== null ? PartnerCompany::query()->find($partnerId) : null;

        if (! $partner instanceof PartnerCompany) {
            return [];
        }

        $type ??= PartnerAgreementType::Initial;
        $previous = app(PartnerAgreementRules::class)->resolvePreviousAgreement($partner, $type);
        $source = $previous ?? $partner;
        $today = CarbonImmutable::today();

        $defaults = [
            'payment_modality' => $source->payment_modality?->value,
            'credit_limit_usd' => $source->credit_limit_usd,
            'discount_percentage' => $source->discount_percentage,
            'profit_percentage_a' => $source->profit_percentage_a,
            'terms' => $previous?->terms ?? $partner->agreement_terms,
            'auto_renews' => (bool) ($previous?->auto_renews ?? false),
            'renewal_term_months' => $previous?->renewal_term_months,
        ];

        if ($previous === null) {
            return $defaults + [
                'starts_on' => $today->toDateString(),
                'ends_on' => $today->addYear()->subDay()->toDateString(),
            ];
        }

        if ($type === PartnerAgreementType::Renewal) {
            $startsOn = $previous->ends_on->addDay();

            if ($startsOn->lt($today)) {
                $startsOn = $today;
            }

            return $defaults + [
                'starts_on' => $startsOn->toDateString(),
                'ends_on' => $startsOn->addDays($previous->termInDays() - 1)->toDateString(),
            ];
        }

        $startsOn = $today->lt($previous->starts_on) ? $previous->starts_on : $today;

        return $defaults + [
            'starts_on' => $startsOn->toDateString(),
            'ends_on' => $previous->ends_on->toDateString(),
        ];
    }
}
