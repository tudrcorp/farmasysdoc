<?php

namespace App\Support\Orders;

use App\Enums\OrderPartnerCashPaymentMethod;
use App\Enums\OrderPartnerPaymentTerms;
use App\Models\User;

/**
 * Normaliza campos de entrega/pago aliado al guardar según rol y compañía del pedido.
 */
final class PartnerOrderFormMutator
{
    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function sanitizeForSave(array $data, ?User $user): array
    {
        if (! $user instanceof User) {
            self::stripAllPartnerDeliveryFields($data);

            return $data;
        }

        if ($user->isPartnerCompanyUser() && ! $user->isAdministrator()) {
            if (($data['partner_payment_terms'] ?? null) === OrderPartnerPaymentTerms::Credit->value) {
                $data['partner_cash_payment_method'] = null;
                self::stripPartnerPaymentReferenceFields($data);
                $data['partner_cash_payment_proof_path'] = null;
            } else {
                self::normalizePartnerPaymentReferenceFields($data);
            }

            return $data;
        }

        if ($user->isAdministrator()) {
            if (empty($data['partner_company_id'] ?? null)) {
                $data['partner_fulfillment_type'] = null;
                $data['partner_payment_terms'] = null;
                $data['partner_cash_payment_method'] = null;
                self::stripPartnerPaymentReferenceFields($data);
                $data['partner_cash_payment_proof_path'] = null;
            } elseif (($data['partner_payment_terms'] ?? null) === OrderPartnerPaymentTerms::Credit->value) {
                $data['partner_cash_payment_method'] = null;
                self::stripPartnerPaymentReferenceFields($data);
                $data['partner_cash_payment_proof_path'] = null;
            } else {
                self::normalizePartnerPaymentReferenceFields($data);
            }

            return $data;
        }

        self::stripAllPartnerDeliveryFields($data);

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function stripAllPartnerDeliveryFields(array &$data): void
    {
        unset(
            $data['partner_fulfillment_type'],
            $data['partner_payment_terms'],
            $data['partner_cash_payment_method'],
            $data['partner_pago_movil_reference'],
            $data['partner_zelle_reference_email'],
            $data['partner_zelle_reference_name'],
            $data['partner_zelle_transaction_number'],
            $data['partner_cash_payment_proof_path'],
        );
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function stripPartnerPaymentReferenceFields(array &$data): void
    {
        $data['partner_pago_movil_reference'] = null;
        $data['partner_zelle_reference_email'] = null;
        $data['partner_zelle_reference_name'] = null;
        $data['partner_zelle_transaction_number'] = null;
    }

    /**
     * Evita datos cruzados (p. ej. referencia Zelle guardada tras cambiar a pago móvil).
     *
     * @param  array<string, mixed>  $data
     */
    private static function normalizePartnerPaymentReferenceFields(array &$data): void
    {
        if (($data['partner_payment_terms'] ?? null) !== OrderPartnerPaymentTerms::Cash->value) {
            self::stripPartnerPaymentReferenceFields($data);
            $data['partner_cash_payment_proof_path'] = null;

            return;
        }

        $method = (string) ($data['partner_cash_payment_method'] ?? '');

        if ($method !== OrderPartnerCashPaymentMethod::PagoMovil->value) {
            $data['partner_pago_movil_reference'] = null;
        }

        if ($method !== OrderPartnerCashPaymentMethod::Zelle->value) {
            $data['partner_zelle_reference_email'] = null;
            $data['partner_zelle_reference_name'] = null;
            $data['partner_zelle_transaction_number'] = null;
        }

        if ($method === OrderPartnerCashPaymentMethod::Transferencia->value || $method === '') {
            self::stripPartnerPaymentReferenceFields($data);
        }
    }
}
