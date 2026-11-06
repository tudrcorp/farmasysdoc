<?php

namespace App\Services\Partners;

use App\Enums\PartnerAgreementEventType;
use App\Enums\PartnerAgreementStatus;
use App\Models\PartnerCompanyAgreement;
use App\Models\PartnerCompanyAgreementEvent;
use App\Models\PartnerCompanyAgreementNotice;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Proceso diario e idempotente de convenios (se puede correr varias veces el mismo día sin efectos dobles):
 *  1. Pone en vigencia los aprobados cuya fecha de inicio llegó.
 *  2. Cierra los vigentes cuya fecha de fin pasó (renovación automática o vencido).
 *  3. Envía avisos de vencimiento por umbral, una sola vez por convenio y umbral.
 *
 * Cada convenio se procesa en su propia transacción: un error se registra y no detiene al resto.
 */
final class PartnerAgreementLifecycleRunner
{
    public function __construct(
        private PartnerAgreementWorkflow $workflow,
        private PartnerAgreementNotifier $notifier,
    ) {}

    /**
     * @return array{activated: int, renewed: int, expired: int, notices: int, errors: int}
     */
    public function run(?CarbonImmutable $today = null): array
    {
        $today = ($today ?? CarbonImmutable::today())->startOfDay();
        $summary = ['activated' => 0, 'renewed' => 0, 'expired' => 0, 'notices' => 0, 'errors' => 0];

        PartnerCompanyAgreement::query()
            ->where('status', PartnerAgreementStatus::Approved->value)
            ->whereDate('starts_on', '<=', $today->toDateString())
            ->orderBy('starts_on')
            ->each(function (PartnerCompanyAgreement $agreement) use ($today, &$summary): void {
                $this->guard($agreement, $summary, function () use ($agreement, $today, &$summary): void {
                    if ($this->workflow->activateIfDue($agreement, $today)) {
                        $summary['activated']++;
                    }
                });
            });

        PartnerCompanyAgreement::query()
            ->where('status', PartnerAgreementStatus::Active->value)
            ->whereDate('ends_on', '<', $today->toDateString())
            ->orderBy('ends_on')
            ->each(function (PartnerCompanyAgreement $agreement) use ($today, &$summary): void {
                $this->guard($agreement, $summary, function () use ($agreement, $today, &$summary): void {
                    $outcome = $this->workflow->closeIfEnded($agreement, $today);

                    if ($outcome !== null) {
                        $summary[$outcome]++;
                    }
                });
            });

        $thresholds = $this->thresholds();

        if ($thresholds !== []) {
            PartnerCompanyAgreement::query()
                ->expiringWithin(max($thresholds), $today)
                ->with('partnerCompany')
                ->each(function (PartnerCompanyAgreement $agreement) use ($today, $thresholds, &$summary): void {
                    $this->guard($agreement, $summary, function () use ($agreement, $today, $thresholds, &$summary): void {
                        if ($this->sendExpiryNoticeIfDue($agreement, $today, $thresholds)) {
                            $summary['notices']++;
                        }
                    });
                });
        }

        return $summary;
    }

    /**
     * Envía solo el umbral más cercano alcanzado y marca también los mayores como enviados, para no
     * disparar varios avisos juntos (por ejemplo, un convenio cargado a 10 días del fin).
     *
     * @param  list<int>  $thresholds
     */
    public function sendExpiryNoticeIfDue(PartnerCompanyAgreement $agreement, CarbonImmutable $today, array $thresholds): bool
    {
        $daysLeft = (int) $today->diffInDays($agreement->ends_on->startOfDay(), false);

        if ($daysLeft < 0) {
            return false;
        }

        $hasApprovedSuccessor = PartnerCompanyAgreement::query()
            ->where('partner_company_id', $agreement->partner_company_id)
            ->where('status', PartnerAgreementStatus::Approved->value)
            ->exists();

        if ($hasApprovedSuccessor) {
            return false;
        }

        $reached = array_values(array_filter($thresholds, fn (int $threshold): bool => $daysLeft <= $threshold));

        if ($reached === []) {
            return false;
        }

        $sent = DB::transaction(function () use ($agreement, $reached, $daysLeft): bool {
            $alreadySent = $agreement->notices()->whereIn('threshold_days', $reached)->pluck('threshold_days')->map(fn ($days): int => (int) $days)->all();
            $pending = array_values(array_diff($reached, $alreadySent));

            if ($pending === []) {
                return false;
            }

            $now = now();
            $inserted = PartnerCompanyAgreementNotice::query()->insertOrIgnore(array_map(fn (int $threshold): array => [
                'partner_company_agreement_id' => $agreement->getKey(),
                'threshold_days' => $threshold,
                'sent_at' => $now,
            ], $pending));

            if ($inserted === 0) {
                return false;
            }

            PartnerCompanyAgreementEvent::query()->create([
                'partner_company_agreement_id' => $agreement->getKey(),
                'type' => PartnerAgreementEventType::ExpiryNoticeSent,
                'notes' => 'Aviso a '.$daysLeft.' '.($daysLeft === 1 ? 'día' : 'días').' del vencimiento.',
                'meta' => ['days_left' => $daysLeft, 'thresholds' => $pending],
            ]);

            return true;
        });

        if ($sent) {
            $this->notifier->notify($agreement, PartnerAgreementEventType::ExpiryNoticeSent, null, ['days' => $daysLeft]);
        }

        return $sent;
    }

    /**
     * @return list<int>
     */
    private function thresholds(): array
    {
        $values = array_map('intval', (array) config('partner_agreements.expiry_notice_days', []));
        $values = array_values(array_unique(array_filter($values, fn (int $days): bool => $days >= 0)));
        sort($values);

        return $values;
    }

    /**
     * @param  array{activated: int, renewed: int, expired: int, notices: int, errors: int}  $summary
     */
    private function guard(PartnerCompanyAgreement $agreement, array &$summary, callable $callback): void
    {
        try {
            $callback();
        } catch (Throwable $exception) {
            $summary['errors']++;

            Log::error('Convenios: error en el proceso diario', [
                'agreement_id' => $agreement->getKey(),
                'number' => $agreement->number,
                'error' => $exception->getMessage(),
            ]);

            report($exception);
        }
    }
}
