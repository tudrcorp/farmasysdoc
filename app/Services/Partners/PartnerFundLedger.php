<?php

namespace App\Services\Partners;

use App\Enums\PartnerFundMovementType;
use App\Models\PartnerAgreementSale;
use App\Models\PartnerCompanyPlan;
use App\Models\PartnerFundMovement;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Libro del fondo de consumo (plan Flexible). Toda escritura bloquea la fila de la asignación
 * (`partner_company_plan`) para que dos cajas no consuman el mismo saldo a la vez.
 */
class PartnerFundLedger
{
    /**
     * Bloquea la asignación hasta el final de la transacción en curso.
     */
    public function lock(int $assignmentId): PartnerCompanyPlan
    {
        $assignment = PartnerCompanyPlan::query()->whereKey($assignmentId)->lockForUpdate()->first();

        if (! $assignment instanceof PartnerCompanyPlan) {
            throw ValidationException::withMessages(['fund' => 'El plan del convenio ya no está asignado a este aliado.']);
        }

        return $assignment;
    }

    public function available(PartnerCompanyPlan $assignment): float
    {
        return $assignment->fundAvailableUsd();
    }

    /**
     * Registra un movimiento (el monto lleva signo) y devuelve el disponible resultante.
     */
    public function record(
        PartnerCompanyPlan $assignment,
        PartnerFundMovementType $type,
        float $amountUsd,
        ?User $actor = null,
        ?PartnerAgreementSale $sale = null,
        ?string $description = null,
        ?float $vesPerUsd = null,
    ): PartnerFundMovement {
        $balanceAfter = round($this->available($assignment) + $amountUsd, 2);

        return PartnerFundMovement::query()->create([
            'partner_company_plan_id' => $assignment->getKey(),
            'partner_company_id' => $assignment->partner_company_id,
            'type' => $type,
            'amount_usd' => round($amountUsd, 2),
            'amount_ves' => $vesPerUsd !== null && $vesPerUsd > 0 ? round($amountUsd * $vesPerUsd, 2) : null,
            'bcv_ves_per_usd' => $vesPerUsd !== null && $vesPerUsd > 0 ? $vesPerUsd : null,
            'balance_after_usd' => $balanceAfter,
            'partner_agreement_sale_id' => $sale?->getKey(),
            'description' => $description,
            'created_by' => $actor?->getKey(),
        ]);
    }

    /**
     * Recarga manual del fondo (Farmadoc). Queda en el libro con su motivo.
     */
    public function recharge(PartnerCompanyPlan $assignment, float $amountUsd, User $actor, string $reason): PartnerFundMovement
    {
        if ($amountUsd <= 0) {
            throw ValidationException::withMessages(['amount_usd' => 'El monto de la recarga debe ser mayor que cero.']);
        }

        return DB::transaction(function () use ($assignment, $amountUsd, $actor, $reason): PartnerFundMovement {
            $locked = $this->lock((int) $assignment->getKey());

            $movement = $this->record($locked, PartnerFundMovementType::Recharge, $amountUsd, $actor, description: $reason);

            PartnerPanelActivityRecorder::record((int) $locked->partner_company_id, 'fund_recharged', 'Recargó el fondo de consumo con $'.number_format($amountUsd, 2, ',', '.').'. Motivo: '.$reason, $actor, $locked);

            return $movement;
        });
    }
}
