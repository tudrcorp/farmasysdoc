<?php

namespace App\Services\Partners;

use App\Enums\PartnerAgreementStatus;
use App\Enums\PartnerAgreementType;
use App\Enums\PartnerCompanyPaymentModality;
use App\Models\PartnerCompany;
use App\Models\PartnerCompanyAgreement;
use Carbon\CarbonImmutable;

/**
 * Reglas de integridad de convenios. Se evalúan al guardar el borrador, al enviarlo y al aprobarlo
 * (con el aliado bloqueado), así que un convenio nunca entra en vigencia en un estado inconsistente.
 */
final class PartnerAgreementRules
{
    /**
     * Convenio del que parte una renovación/modificación: el vigente; si no hay vigente, para renovaciones,
     * el último vencido.
     */
    public function resolvePreviousAgreement(PartnerCompany $partner, PartnerAgreementType $type, ?int $exceptId = null): ?PartnerCompanyAgreement
    {
        if (! $type->requiresPreviousAgreement()) {
            return null;
        }

        $active = $partner->agreements()
            ->where('status', PartnerAgreementStatus::Active->value)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->orderByDesc('starts_on')
            ->first();

        if ($active !== null || $type !== PartnerAgreementType::Renewal) {
            return $active;
        }

        return $partner->agreements()
            ->where('status', PartnerAgreementStatus::Expired->value)
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->orderByDesc('ends_on')
            ->first();
    }

    /**
     * Valida el convenio (persistido o no) y fija `previous_agreement_id` según el estado actual del aliado.
     *
     * @throws PartnerAgreementException
     */
    public function assertValid(PartnerCompanyAgreement $agreement): void
    {
        $partner = $agreement->partnerCompany;

        if (! $partner instanceof PartnerCompany) {
            throw new PartnerAgreementException('Seleccione la compañía aliada del convenio.');
        }

        $type = $agreement->type;
        $startsOn = $agreement->starts_on;
        $endsOn = $agreement->ends_on;

        if (! $type instanceof PartnerAgreementType || $startsOn === null || $endsOn === null) {
            throw new PartnerAgreementException('El tipo de convenio y las fechas de inicio y fin son obligatorios.');
        }

        if ($endsOn->lt($startsOn)) {
            throw new PartnerAgreementException('La fecha de fin no puede ser anterior a la fecha de inicio.');
        }

        $this->assertCommercialTerms($agreement);

        if ($type->requiresPreviousAgreement() && blank($agreement->change_reason)) {
            throw new PartnerAgreementException('Indique el motivo de la '.mb_strtolower($type->label()).'.');
        }

        if ($agreement->auto_renews && $agreement->renewal_term_months !== null && $agreement->renewal_term_months < 1) {
            throw new PartnerAgreementException('El plazo de renovación automática debe ser de al menos 1 mes.');
        }

        $exceptId = $agreement->exists ? (int) $agreement->getKey() : null;

        $this->assertSingleInFlight($partner, $exceptId);

        $previous = $this->resolvePreviousAgreement($partner, $type, $exceptId);
        $this->assertPreviousAgreement($type, $previous, $startsOn, $partner, $exceptId);

        $agreement->previous_agreement_id = $previous?->getKey();

        $this->assertNoOverlap($partner, $startsOn, $endsOn, $exceptId, $type->changesWithinTerm() ? $previous?->getKey() : null);
    }

    /**
     * @throws PartnerAgreementException
     */
    private function assertCommercialTerms(PartnerCompanyAgreement $agreement): void
    {
        if (! $agreement->payment_modality instanceof PartnerCompanyPaymentModality) {
            throw new PartnerAgreementException('Seleccione la modalidad de pago del convenio.');
        }

        if ($agreement->payment_modality->usesCreditLimit()) {
            if ($agreement->credit_limit_usd === null || (float) $agreement->credit_limit_usd <= 0) {
                throw new PartnerAgreementException('La modalidad '.$agreement->payment_modality->label().' requiere un límite de crédito mayor a cero.');
            }
        } elseif ($agreement->credit_limit_usd !== null) {
            $agreement->credit_limit_usd = null;
        }

        foreach (['discount_percentage' => 'descuento', 'profit_percentage_a' => 'ganancia'] as $attribute => $label) {
            $value = $agreement->getAttribute($attribute);

            if ($value !== null && ((float) $value < 0 || (float) $value > 100)) {
                throw new PartnerAgreementException("El porcentaje de {$label} debe estar entre 0 y 100.");
            }
        }
    }

    /**
     * @throws PartnerAgreementException
     */
    private function assertSingleInFlight(PartnerCompany $partner, ?int $exceptId): void
    {
        $other = $partner->agreements()
            ->whereIn('status', array_map(fn (PartnerAgreementStatus $status): string => $status->value, PartnerAgreementStatus::inFlight()))
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->first();

        if ($other !== null) {
            throw new PartnerAgreementException(sprintf(
                'El aliado ya tiene un convenio en trámite (%s, %s). Termine de gestionarlo o anúlelo antes de crear otro.',
                $other->number ?? 'borrador #'.$other->getKey(),
                mb_strtolower($other->status->label()),
            ));
        }
    }

    /**
     * @throws PartnerAgreementException
     */
    private function assertPreviousAgreement(
        PartnerAgreementType $type,
        ?PartnerCompanyAgreement $previous,
        CarbonImmutable $startsOn,
        PartnerCompany $partner,
        ?int $exceptId,
    ): void {
        if ($type === PartnerAgreementType::Initial) {
            $hasTerm = $partner->agreements()
                ->whereIn('status', [PartnerAgreementStatus::Active->value, PartnerAgreementStatus::Approved->value])
                ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
                ->exists();

            if ($hasTerm) {
                throw new PartnerAgreementException('El aliado ya tiene un convenio vigente o aprobado. Use renovación, modificación o renegociación.');
            }

            return;
        }

        if ($previous === null) {
            throw new PartnerAgreementException($type === PartnerAgreementType::Renewal
                ? 'El aliado no tiene un convenio vigente ni vencido que renovar. Registre un convenio inicial.'
                : 'Solo se puede modificar o renegociar un convenio vigente.');
        }

        if ($type === PartnerAgreementType::Renewal) {
            if ($startsOn->lte($previous->ends_on)) {
                throw new PartnerAgreementException(sprintf(
                    'La renovación debe iniciar después del fin del convenio %s (%s).',
                    $previous->number,
                    $previous->ends_on->format('d/m/Y'),
                ));
            }

            return;
        }

        if ($startsOn->lt($previous->starts_on) || $startsOn->gt($previous->ends_on)) {
            throw new PartnerAgreementException(sprintf(
                'La %s debe iniciar dentro de la vigencia del convenio %s (%s).',
                mb_strtolower($type->label()),
                $previous->number,
                $previous->termLabel(),
            ));
        }
    }

    /**
     * @throws PartnerAgreementException
     */
    private function assertNoOverlap(PartnerCompany $partner, CarbonImmutable $startsOn, CarbonImmutable $endsOn, ?int $exceptId, ?int $replacedId): void
    {
        $overlapping = $partner->agreements()
            ->whereIn('status', array_map(fn (PartnerAgreementStatus $status): string => $status->value, PartnerAgreementStatus::occupyingTerm()))
            ->when($exceptId !== null, fn ($query) => $query->whereKeyNot($exceptId))
            ->when($replacedId !== null, fn ($query) => $query->whereKeyNot($replacedId))
            ->whereDate('starts_on', '<=', $endsOn->toDateString())
            ->whereDate('ends_on', '>=', $startsOn->toDateString())
            ->first();

        if ($overlapping !== null) {
            throw new PartnerAgreementException(sprintf(
                'Las fechas se solapan con el convenio %s (%s).',
                $overlapping->number,
                $overlapping->termLabel(),
            ));
        }
    }
}
