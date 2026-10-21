<?php

namespace App\Services\Finance;

use App\Enums\PettyCashFundStatus;
use App\Models\PettyCashFund;
use App\Models\User;
use App\Support\Finance\NotifyRequesterPettyCashDecision;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class PettyCashFundApprover
{
    public function __construct(
        private readonly NotifyRequesterPettyCashDecision $notifyRequesterPettyCashDecision,
    ) {}

    public function approve(PettyCashFund $fund, User $admin, string $paymentProofPath, string $paymentReference): PettyCashFund
    {
        if (! $admin->isAdministrator()) {
            throw new RuntimeException('Solo un administrador puede aprobar la caja chica.');
        }

        $proof = trim($paymentProofPath);
        $reference = trim($paymentReference);
        if ($proof === '' || $reference === '') {
            throw new InvalidArgumentException('Debe adjuntar el comprobante y la referencia del pago.');
        }

        $approved = DB::transaction(function () use ($fund, $admin, $proof, $reference): PettyCashFund {
            $locked = PettyCashFund::query()->whereKey($fund->getKey())->lockForUpdate()->first();
            if (! $locked instanceof PettyCashFund) {
                throw new RuntimeException('No se encontró la solicitud de caja chica.');
            }

            if ($locked->status !== PettyCashFundStatus::PendingApproval) {
                throw new InvalidArgumentException('La solicitud no está pendiente de aprobación.');
            }

            $locked->forceFill([
                'status' => PettyCashFundStatus::Approved,
                'approved_by' => $admin->id,
                'approved_at' => now(),
                'payment_proof_path' => $proof,
                'payment_reference' => $reference,
                'rejection_reason' => null,
                'remaining_usd' => $locked->amount_usd,
                'remaining_ves' => $locked->amount_ves,
            ])->save();

            return $locked->refresh();
        });

        $this->notifyRequester($approved);

        return $approved;
    }

    public function reject(PettyCashFund $fund, User $admin, ?string $reason = null): PettyCashFund
    {
        if (! $admin->isAdministrator()) {
            throw new RuntimeException('Solo un administrador puede rechazar la caja chica.');
        }

        $rejected = DB::transaction(function () use ($fund, $admin, $reason): PettyCashFund {
            $locked = PettyCashFund::query()->whereKey($fund->getKey())->lockForUpdate()->first();
            if (! $locked instanceof PettyCashFund) {
                throw new RuntimeException('No se encontró la solicitud de caja chica.');
            }

            if ($locked->status !== PettyCashFundStatus::PendingApproval) {
                throw new InvalidArgumentException('La solicitud no está pendiente de aprobación.');
            }

            if ($locked->replenished_from_fund_id !== null) {
                $predecessor = PettyCashFund::query()
                    ->whereKey($locked->replenished_from_fund_id)
                    ->lockForUpdate()
                    ->first();

                if ($predecessor instanceof PettyCashFund
                    && $predecessor->status === PettyCashFundStatus::Replenished
                    && (int) $predecessor->superseded_by_fund_id === (int) $locked->getKey()) {
                    $predecessor->forceFill([
                        'status' => PettyCashFundStatus::Approved,
                        'superseded_by_fund_id' => null,
                        'remaining_usd' => $locked->carried_over_usd,
                        'remaining_ves' => $locked->carried_over_ves,
                    ])->save();
                }
            }

            $locked->forceFill([
                'status' => PettyCashFundStatus::Rejected,
                'approved_by' => $admin->id,
                'approved_at' => now(),
                'rejection_reason' => filled($reason) ? trim($reason) : null,
            ])->save();

            return $locked->refresh();
        });

        $this->notifyRequester($rejected);

        return $rejected;
    }

    private function notifyRequester(PettyCashFund $fund): void
    {
        try {
            $this->notifyRequesterPettyCashDecision->notify($fund);
        } catch (Throwable $exception) {
            Log::warning('No se pudo enviar WhatsApp de decisión de caja chica', [
                'petty_cash_fund_id' => $fund->getKey(),
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
