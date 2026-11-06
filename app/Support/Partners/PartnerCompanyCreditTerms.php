<?php

namespace App\Support\Partners;

use App\Enums\PartnerCompanyPaymentModality;

/**
 * Normaliza límite de crédito (`credit_limit_usd`) y saldo disponible (`assigned_credit_limit`)
 * según la modalidad de pago antes de guardar una compañía aliada.
 */
final class PartnerCompanyCreditTerms
{
    /**
     * - Contado (o sin modalidad): sin límite ni saldo.
     * - Crédito / integral: al crear, si el saldo viene vacío se inicia igual al límite.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalize(array $data, bool $isCreating): array
    {
        $modality = $data['payment_modality'] ?? null;
        $modality = $modality instanceof PartnerCompanyPaymentModality
            ? $modality
            : PartnerCompanyPaymentModality::tryFrom((string) $modality);

        if ($modality === null || ! $modality->usesCreditLimit()) {
            $data['credit_limit_usd'] = null;
            $data['assigned_credit_limit'] = null;

            return $data;
        }

        $limit = self::toAmount($data['credit_limit_usd'] ?? null);
        $balance = self::toAmount($data['assigned_credit_limit'] ?? null);

        if ($isCreating && $balance === null) {
            $balance = $limit;
        }

        $data['credit_limit_usd'] = $limit;
        $data['assigned_credit_limit'] = $balance;

        return $data;
    }

    private static function toAmount(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        return round((float) $value, 2);
    }
}
