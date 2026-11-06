<?php

namespace App\Services\Partners;

use App\Enums\PartnerAgreementEventType;
use App\Enums\PartnerAgreementStatus;
use App\Enums\PartnerAgreementType;
use App\Models\PartnerCompany;
use App\Models\PartnerCompanyAgreement;
use App\Models\PartnerCompanyAgreementEvent;
use App\Models\User;
use Carbon\CarbonImmutable;
use Closure;
use Illuminate\Support\Facades\DB;

/**
 * Única vía para crear convenios y moverlos de estado.
 *
 * Cada transición corre en una transacción que bloquea primero al aliado y luego al convenio
 * (mismo orden siempre, para evitar interbloqueos), vuelve a leer el estado bloqueado, valida con
 * {@see PartnerAgreementRules} y deja constancia en la bitácora. Los avisos salen después del commit.
 */
final class PartnerAgreementWorkflow
{
    public function __construct(
        private PartnerAgreementRules $rules,
        private PartnerAgreementTermsApplier $termsApplier,
        private PartnerAgreementNotifier $notifier,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws PartnerAgreementException
     */
    public function createDraft(array $data, User $actor): PartnerCompanyAgreement
    {
        return DB::transaction(function () use ($data, $actor): PartnerCompanyAgreement {
            $partner = $this->lockPartner((int) ($data['partner_company_id'] ?? 0));

            $agreement = new PartnerCompanyAgreement;
            $agreement->fill($data);
            $agreement->setRelation('partnerCompany', $partner);
            $agreement->forceFill([
                'status' => PartnerAgreementStatus::Draft,
                'created_by' => $actor->getKey(),
                'updated_by' => $actor->getKey(),
            ]);

            $this->rules->assertValid($agreement);

            $agreement->save();
            $agreement->forceFill([
                'number' => PartnerCompanyAgreement::formatNumber($agreement->getKey()),
            ])->save();

            $this->log($agreement, PartnerAgreementEventType::Created, null, PartnerAgreementStatus::Draft, $actor);

            return $agreement;
        });
    }

    /**
     * Edita un borrador o un rechazado (un rechazado vuelve a borrador).
     *
     * @param  array<string, mixed>  $data
     *
     * @throws PartnerAgreementException
     */
    public function updateDraft(PartnerCompanyAgreement $agreement, array $data, User $actor): PartnerCompanyAgreement
    {
        return $this->transition($agreement, function (PartnerCompanyAgreement $locked, PartnerCompany $partner) use ($data, $actor): void {
            if (! $locked->status->isEditable()) {
                throw new PartnerAgreementException('Solo se pueden editar convenios en borrador o rechazados. Para cambiar uno vigente cree una modificación.');
            }

            unset($data['partner_company_id']);

            $from = $locked->status;
            $locked->fill($data);
            $locked->forceFill([
                'status' => PartnerAgreementStatus::Draft,
                'updated_by' => $actor->getKey(),
            ]);

            $this->rules->assertValid($locked);
            $locked->save();

            $this->log($locked, PartnerAgreementEventType::Updated, $from, PartnerAgreementStatus::Draft, $actor, meta: [
                'changed' => array_keys($locked->getChanges()),
            ]);
        });
    }

    /**
     * @throws PartnerAgreementException
     */
    public function submit(PartnerCompanyAgreement $agreement, User $actor): PartnerCompanyAgreement
    {
        $agreement = $this->transition($agreement, function (PartnerCompanyAgreement $locked) use ($actor): void {
            if (! $locked->status->isEditable()) {
                throw new PartnerAgreementException('Solo se envían a aprobación convenios en borrador o rechazados.');
            }

            $this->assertSignedDocument($locked);
            $this->rules->assertValid($locked);

            $from = $locked->status;
            $locked->forceFill([
                'status' => PartnerAgreementStatus::PendingApproval,
                'submitted_by' => $actor->getKey(),
                'submitted_at' => now(),
                'rejected_by' => null,
                'rejected_at' => null,
                'rejection_reason' => null,
            ])->save();

            $this->log($locked, PartnerAgreementEventType::Submitted, $from, PartnerAgreementStatus::PendingApproval, $actor);
        });

        $this->notifier->notify($agreement, PartnerAgreementEventType::Submitted, $actor);

        return $agreement;
    }

    /**
     * Solo administradores. Si la fecha de inicio ya llegó, entra en vigencia en la misma transacción.
     *
     * @throws PartnerAgreementException
     */
    public function approve(PartnerCompanyAgreement $agreement, User $actor, ?string $notes = null, ?CarbonImmutable $today = null): PartnerCompanyAgreement
    {
        $this->assertAdministrator($actor);
        $today ??= CarbonImmutable::today();
        $activated = false;

        $agreement = $this->transition($agreement, function (PartnerCompanyAgreement $locked, PartnerCompany $partner) use ($actor, $notes, $today, &$activated): void {
            if ($locked->status !== PartnerAgreementStatus::PendingApproval) {
                throw new PartnerAgreementException('Solo se aprueban convenios que están por aprobar.');
            }

            $this->assertSignedDocument($locked);
            $this->rules->assertValid($locked);

            if ($locked->ends_on->lt($today)) {
                throw new PartnerAgreementException('No se puede aprobar un convenio cuya fecha de fin ya pasó. Corrija las fechas.');
            }

            $locked->forceFill([
                'status' => PartnerAgreementStatus::Approved,
                'approved_by' => $actor->getKey(),
                'approved_at' => now(),
            ])->save();

            $this->log($locked, PartnerAgreementEventType::Approved, PartnerAgreementStatus::PendingApproval, PartnerAgreementStatus::Approved, $actor, $notes);

            if ($locked->starts_on->lte($today)) {
                $this->activateLocked($locked, $partner, $actor, $today);
                $activated = true;
            }
        });

        $this->notifier->notify($agreement, $activated ? PartnerAgreementEventType::Activated : PartnerAgreementEventType::Approved, $actor);

        return $agreement;
    }

    /**
     * @throws PartnerAgreementException
     */
    public function reject(PartnerCompanyAgreement $agreement, User $actor, string $reason): PartnerCompanyAgreement
    {
        $this->assertAdministrator($actor);

        if (blank(trim($reason))) {
            throw new PartnerAgreementException('Indique el motivo del rechazo.');
        }

        $agreement = $this->transition($agreement, function (PartnerCompanyAgreement $locked) use ($actor, $reason): void {
            if ($locked->status !== PartnerAgreementStatus::PendingApproval) {
                throw new PartnerAgreementException('Solo se rechazan convenios que están por aprobar.');
            }

            $locked->forceFill([
                'status' => PartnerAgreementStatus::Rejected,
                'rejected_by' => $actor->getKey(),
                'rejected_at' => now(),
                'rejection_reason' => trim($reason),
            ])->save();

            $this->log($locked, PartnerAgreementEventType::Rejected, PartnerAgreementStatus::PendingApproval, PartnerAgreementStatus::Rejected, $actor, trim($reason));
        });

        $this->notifier->notify($agreement, PartnerAgreementEventType::Rejected, $actor);

        return $agreement;
    }

    /**
     * Anula un convenio en trámite, aprobado o vigente (terminación anticipada). Solo administradores.
     * Anular un vigente no revierte las condiciones ya aplicadas al aliado.
     *
     * @throws PartnerAgreementException
     */
    public function cancel(PartnerCompanyAgreement $agreement, User $actor, string $reason): PartnerCompanyAgreement
    {
        $this->assertAdministrator($actor);

        if (blank(trim($reason))) {
            throw new PartnerAgreementException('Indique el motivo de la anulación.');
        }

        $agreement = $this->transition($agreement, function (PartnerCompanyAgreement $locked) use ($actor, $reason): void {
            $cancellable = [...PartnerAgreementStatus::inFlight(), PartnerAgreementStatus::Active];

            if (! in_array($locked->status, $cancellable, true)) {
                throw new PartnerAgreementException('Este convenio ya está cerrado ('.mb_strtolower($locked->status->label()).').');
            }

            $from = $locked->status;
            $locked->forceFill([
                'status' => PartnerAgreementStatus::Cancelled,
                'cancelled_by' => $actor->getKey(),
                'cancelled_at' => now(),
                'cancellation_reason' => trim($reason),
                'closed_at' => now(),
            ])->save();

            $this->log($locked, PartnerAgreementEventType::Cancelled, $from, PartnerAgreementStatus::Cancelled, $actor, trim($reason));
        });

        $this->notifier->notify($agreement, PartnerAgreementEventType::Cancelled, $actor);

        return $agreement;
    }

    /**
     * Pone en vigencia un convenio aprobado cuya fecha de inicio llegó (proceso diario).
     */
    public function activateIfDue(PartnerCompanyAgreement $agreement, CarbonImmutable $today): bool
    {
        $activated = false;

        $agreement = $this->transition($agreement, function (PartnerCompanyAgreement $locked, PartnerCompany $partner) use ($today, &$activated): void {
            if ($locked->status !== PartnerAgreementStatus::Approved || $locked->starts_on->gt($today)) {
                return;
            }

            $this->activateLocked($locked, $partner, null, $today);
            $activated = true;
        });

        if ($activated) {
            $this->notifier->notify($agreement, PartnerAgreementEventType::Activated);
        }

        return $activated;
    }

    /**
     * Cierra un vigente cuya fecha de fin pasó: renueva automáticamente si corresponde; si no, lo marca vencido.
     *
     * @return 'renewed'|'expired'|null
     */
    public function closeIfEnded(PartnerCompanyAgreement $agreement, CarbonImmutable $today): ?string
    {
        $outcome = null;
        $renewal = null;

        $agreement = $this->transition($agreement, function (PartnerCompanyAgreement $locked, PartnerCompany $partner) use ($today, &$outcome, &$renewal): void {
            if ($locked->status !== PartnerAgreementStatus::Active || $locked->ends_on->gte($today)) {
                return;
            }

            $renewalInProgress = $this->renewalInProgress($partner, $locked);

            $this->markEnded($locked, PartnerAgreementStatus::Expired, PartnerAgreementEventType::Expired, $locked->auto_renews && $renewalInProgress !== null
                ? 'No se renovó automáticamente porque hay una '.mb_strtolower($renewalInProgress->type->label()).' en trámite ('.$renewalInProgress->number.').'
                : null);

            if ($locked->auto_renews && $renewalInProgress === null) {
                $renewal = $this->createAutomaticRenewal($locked, $partner, $today);
                $outcome = 'renewed';

                return;
            }

            $outcome = 'expired';
        });

        if ($renewal instanceof PartnerCompanyAgreement) {
            $this->notifier->notify($renewal, PartnerAgreementEventType::AutoRenewed);
        } elseif ($outcome === 'expired') {
            $this->notifier->notify($agreement, PartnerAgreementEventType::Expired);
        }

        return $outcome;
    }

    private function activateLocked(PartnerCompanyAgreement $locked, PartnerCompany $partner, ?User $actor, CarbonImmutable $today): void
    {
        $current = $partner->agreements()
            ->where('status', PartnerAgreementStatus::Active->value)
            ->whereKeyNot($locked->getKey())
            ->lockForUpdate()
            ->get();

        foreach ($current as $previous) {
            if ($previous->ends_on->lt($locked->starts_on) && $previous->ends_on->lt($today)) {
                $this->markEnded($previous, PartnerAgreementStatus::Expired, PartnerAgreementEventType::Expired);

                continue;
            }

            $this->markEnded($previous, PartnerAgreementStatus::Superseded, PartnerAgreementEventType::Superseded, 'Reemplazado por '.$locked->number);
        }

        $from = $locked->status;
        $locked->forceFill([
            'status' => PartnerAgreementStatus::Active,
            'activated_at' => now(),
        ])->save();

        $this->log($locked, PartnerAgreementEventType::Activated, $from, PartnerAgreementStatus::Active, $actor);

        $credit = $this->termsApplier->apply($locked, $partner);

        $this->log($locked, PartnerAgreementEventType::TermsApplied, null, null, $actor, $credit['balance_clamped']
            ? 'El nuevo límite es menor que lo ya consumido: el saldo disponible quedó en 0.'
            : null, $credit);
    }

    private function markEnded(PartnerCompanyAgreement $agreement, PartnerAgreementStatus $status, PartnerAgreementEventType $event, ?string $notes = null): void
    {
        $from = $agreement->status;

        $agreement->forceFill([
            'status' => $status,
            'closed_at' => now(),
        ])->save();

        $this->log($agreement, $event, $from, $status, null, $notes);
    }

    /**
     * Renovación o renegociación ya en trámite: tiene prioridad sobre la renovación automática.
     */
    private function renewalInProgress(PartnerCompany $partner, PartnerCompanyAgreement $ending): ?PartnerCompanyAgreement
    {
        return $partner->agreements()
            ->whereKeyNot($ending->getKey())
            ->whereIn('status', array_map(fn (PartnerAgreementStatus $status): string => $status->value, PartnerAgreementStatus::inFlight()))
            ->whereIn('type', [PartnerAgreementType::Renewal->value, PartnerAgreementType::Renegotiation->value])
            ->first();
    }

    private function createAutomaticRenewal(PartnerCompanyAgreement $previous, PartnerCompany $partner, CarbonImmutable $today): PartnerCompanyAgreement
    {
        $startsOn = $previous->ends_on->addDay();
        $endsOn = $previous->renewal_term_months !== null
            ? $startsOn->addMonthsNoOverflow($previous->renewal_term_months)->subDay()
            : $startsOn->addDays($previous->termInDays() - 1);

        while ($endsOn->lt($today)) {
            $startsOn = $endsOn->addDay();
            $endsOn = $previous->renewal_term_months !== null
                ? $startsOn->addMonthsNoOverflow($previous->renewal_term_months)->subDay()
                : $startsOn->addDays($previous->termInDays() - 1);
        }

        $renewal = new PartnerCompanyAgreement;
        $renewal->fill([
            'partner_company_id' => $partner->getKey(),
            'previous_agreement_id' => $previous->getKey(),
            'type' => PartnerAgreementType::Renewal,
            'starts_on' => $startsOn,
            'ends_on' => $endsOn,
            'auto_renews' => true,
            'renewal_term_months' => $previous->renewal_term_months,
            'payment_modality' => $previous->payment_modality,
            'credit_limit_usd' => $previous->credit_limit_usd,
            'discount_percentage' => $previous->discount_percentage,
            'profit_percentage_a' => $previous->profit_percentage_a,
            'terms' => $previous->terms,
            'change_reason' => 'Renovación automática según cláusula del convenio '.$previous->number.'.',
            'signed_document_path' => $previous->signed_document_path,
            'signed_document_name' => $previous->signed_document_name,
            'signed_on' => $previous->signed_on,
        ]);
        $renewal->forceFill([
            'status' => PartnerAgreementStatus::Active,
            'is_automatic' => true,
            'approved_by' => $previous->approved_by,
            'approved_at' => now(),
            'activated_at' => now(),
        ])->save();

        $renewal->forceFill(['number' => PartnerCompanyAgreement::formatNumber($renewal->getKey())])->save();

        $this->log($renewal, PartnerAgreementEventType::AutoRenewed, null, PartnerAgreementStatus::Active, null, 'Renueva '.$previous->number.' con las mismas condiciones.');
        $this->log($previous, PartnerAgreementEventType::AutoRenewed, null, null, null, 'Renovado como '.$renewal->number.'.');

        $credit = $this->termsApplier->apply($renewal, $partner);
        $this->log($renewal, PartnerAgreementEventType::TermsApplied, null, null, null, null, $credit);

        return $renewal;
    }

    /**
     * @param  Closure(PartnerCompanyAgreement, PartnerCompany): void  $callback
     */
    private function transition(PartnerCompanyAgreement $agreement, Closure $callback): PartnerCompanyAgreement
    {
        return DB::transaction(function () use ($agreement, $callback): PartnerCompanyAgreement {
            $partner = $this->lockPartner((int) $agreement->partner_company_id);

            /** @var PartnerCompanyAgreement $locked */
            $locked = PartnerCompanyAgreement::query()->whereKey($agreement->getKey())->lockForUpdate()->firstOrFail();
            $locked->setRelation('partnerCompany', $partner);

            $callback($locked, $partner);

            return $locked;
        });
    }

    private function lockPartner(int $partnerId): PartnerCompany
    {
        $partner = PartnerCompany::query()->whereKey($partnerId)->lockForUpdate()->first();

        if (! $partner instanceof PartnerCompany) {
            throw new PartnerAgreementException('La compañía aliada del convenio no existe.');
        }

        return $partner;
    }

    private function assertAdministrator(User $actor): void
    {
        if (! $actor->isAdministrator()) {
            throw new PartnerAgreementException('Solo un Administrador puede aprobar, rechazar o anular convenios.');
        }
    }

    private function assertSignedDocument(PartnerCompanyAgreement $agreement): void
    {
        if (! $agreement->hasSignedDocument()) {
            throw new PartnerAgreementException('Adjunte el contrato firmado antes de continuar.');
        }
    }

    /**
     * @param  array<string, mixed>|null  $meta
     */
    private function log(
        PartnerCompanyAgreement $agreement,
        PartnerAgreementEventType $type,
        ?PartnerAgreementStatus $from,
        ?PartnerAgreementStatus $to,
        ?User $actor,
        ?string $notes = null,
        ?array $meta = null,
    ): void {
        PartnerCompanyAgreementEvent::query()->create([
            'partner_company_agreement_id' => $agreement->getKey(),
            'type' => $type,
            'from_status' => $from,
            'to_status' => $to,
            'user_id' => $actor?->getKey(),
            'notes' => $notes,
            'meta' => $meta,
        ]);
    }
}
