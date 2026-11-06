<?php

namespace App\Services\Partners;

use App\Models\PartnerCompany;
use App\Models\PartnerCompanyAgreement;
use App\Support\Partners\PartnerCreditLedger;

/**
 * Copia las condiciones de un convenio que entra en vigencia a la ficha del aliado.
 *
 * El saldo de crédito se mueve por la diferencia de límites (`saldo + (límite nuevo − límite anterior)`),
 * nunca por debajo de cero. Debe llamarse con el aliado bloqueado (`lockForUpdate`) dentro de una transacción,
 * igual que {@see PartnerCreditLedger}.
 */
final class PartnerAgreementTermsApplier
{
    /**
     * @return array{previous_limit: ?float, new_limit: ?float, previous_balance: ?float, new_balance: ?float, balance_clamped: bool}
     */
    public function apply(PartnerCompanyAgreement $agreement, PartnerCompany $partner): array
    {
        $previousLimit = $partner->credit_limit_usd !== null ? round((float) $partner->credit_limit_usd, 2) : null;
        $previousBalance = $partner->assigned_credit_limit !== null ? round((float) $partner->assigned_credit_limit, 2) : null;
        $newLimit = $agreement->usesCreditLimit() && $agreement->credit_limit_usd !== null
            ? round((float) $agreement->credit_limit_usd, 2)
            : null;

        $balanceClamped = false;

        if ($newLimit === null) {
            $newBalance = null;
        } elseif ($previousLimit === null) {
            $newBalance = $previousBalance !== null ? min($previousBalance, $newLimit) : $newLimit;
        } else {
            $newBalance = round(($previousBalance ?? 0.0) + ($newLimit - $previousLimit), 2);

            if ($newBalance < 0) {
                $newBalance = 0.0;
                $balanceClamped = true;
            }
        }

        $partner->forceFill([
            'payment_modality' => $agreement->payment_modality,
            'credit_limit_usd' => $newLimit,
            'assigned_credit_limit' => $newBalance,
            'discount_percentage' => $agreement->discount_percentage,
            'profit_percentage_a' => $agreement->profit_percentage_a,
            'agreement_reference' => $agreement->number,
            'agreement_terms' => $agreement->terms,
            'date_created' => $partner->date_created ?? $agreement->starts_on,
            'date_updated' => $agreement->starts_on,
        ])->save();

        return [
            'previous_limit' => $previousLimit,
            'new_limit' => $newLimit,
            'previous_balance' => $previousBalance,
            'new_balance' => $newBalance,
            'balance_clamped' => $balanceClamped,
        ];
    }
}
