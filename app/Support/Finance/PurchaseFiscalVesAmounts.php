<?php

namespace App\Support\Finance;

use App\Enums\PurchaseEntryCurrency;
use App\Models\Purchase;

/**
 * Montos fiscales en bolívares de una compra para Libro de Compras y Retenciones (SENIAT).
 *
 * El total de la factura (A) y el exento (C) se convierten a Bs y se respetan tal cual;
 * la base y el IVA se derivan de ellos, redondeando cada paso a 2 decimales:
 *  - taxable_base_ves = (A − C) ÷ (1 + alícuota)
 *  - tax_caused_ves = taxable_base_ves × alícuota
 *  - retención = tax_caused_ves × % de retención del proveedor
 *
 * Con 2 decimales, B + IVA + C puede diferir 1 céntimo de A; se prioriza A como valor de la factura.
 */
final class PurchaseFiscalVesAmounts
{
    public function __construct(
        public readonly float $taxableBaseVes,
        public readonly float $exemptVes,
        public readonly float $taxCausedVes,
        public readonly float $totalVes,
        public readonly float $vatRatePercent,
    ) {}

    public static function fromPurchase(Purchase $purchase, float $rateAtInvoice, ?float $vatRatePercent = null): self
    {
        $exemptDocument = (float) ($purchase->net_exempt_after_document_discount
            ?? $purchase->subtotal_exempt_amount
            ?? 0);

        $isVes = $purchase->entryCurrency() === PurchaseEntryCurrency::VES;

        return self::fromInvoiceAmounts(
            invoiceTotalDocument: (float) $purchase->total,
            exemptDocument: $exemptDocument,
            rateToVes: $isVes ? 1.0 : $rateAtInvoice,
            vatRatePercent: $vatRatePercent ?? DefaultVatRate::percent(),
        );
    }

    public static function fromInvoiceAmounts(
        float $invoiceTotalDocument,
        float $exemptDocument,
        float $rateToVes,
        float $vatRatePercent,
    ): self {
        $totalCents = self::toCents(max(0.0, $invoiceTotalDocument) * $rateToVes);
        $exemptCents = min($totalCents, self::toCents(max(0.0, $exemptDocument) * $rateToVes));

        $taxableCents = $vatRatePercent > 0
            ? (int) round(($totalCents - $exemptCents) * 100 / (100 + $vatRatePercent), 0, PHP_ROUND_HALF_UP)
            : $totalCents - $exemptCents;
        $taxCents = $vatRatePercent > 0
            ? (int) round($taxableCents * $vatRatePercent / 100, 0, PHP_ROUND_HALF_UP)
            : 0;

        return new self(
            taxableBaseVes: $taxableCents / 100,
            exemptVes: $exemptCents / 100,
            taxCausedVes: $taxCents / 100,
            totalVes: $totalCents / 100,
            vatRatePercent: $vatRatePercent,
        );
    }

    public function retainedVes(float $retentionPercent): float
    {
        $taxCents = (int) round($this->taxCausedVes * 100);

        return ((int) round($taxCents * $retentionPercent / 100, 0, PHP_ROUND_HALF_UP)) / 100;
    }

    private static function toCents(float $amount): int
    {
        return (int) round(round($amount, 6) * 100, 0, PHP_ROUND_HALF_UP);
    }
}
