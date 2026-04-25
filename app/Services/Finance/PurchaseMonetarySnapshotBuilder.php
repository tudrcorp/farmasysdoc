<?php

namespace App\Services\Finance;

use App\Enums\PurchaseEntryCurrency;
use App\Models\Purchase;
use Carbon\CarbonInterface;
use Illuminate\Support\Carbon;

/**
 * Totales de compra en USD y Bs (tasa BCV) alineados con cuentas por pagar / histórico.
 */
final class PurchaseMonetarySnapshotBuilder
{
    public function __construct(
        private readonly VenezuelaOfficialUsdVesRateClient $rateClient,
    ) {}

    /**
     * @return array{
     *     usd_total: float,
     *     issued_at: Carbon,
     *     registered_at: Carbon,
     *     rate_at_issue: float,
     *     rate_at_load: float,
     *     ves_at_issue: float,
     *     ves_at_registration: float,
     *     invoice_number: string,
     *     supplier_name: string,
     *     supplier_tax_id: string|null,
     *     supplier_control_number: string|null,
     * }|null
     */
    public function build(Purchase $purchase): ?array
    {
        $purchase->loadMissing('supplier');

        $usdTotal = $this->documentTotalUsd($purchase);
        if ($usdTotal <= 0) {
            return null;
        }

        $issuedAt = Carbon::parse($purchase->supplier_invoice_date ?? $purchase->registered_in_system_date ?? now())->startOfDay();
        $registeredAt = Carbon::parse($purchase->registered_in_system_date ?? $issuedAt)->startOfDay();

        $rateAtIssue = $this->resolveRateForPurchase($purchase, $issuedAt);
        $rateAtLoad = $this->resolveRateForPurchase($purchase, $registeredAt) ?? $rateAtIssue;

        if ($rateAtIssue === null || $rateAtIssue <= 0 || $rateAtLoad === null || $rateAtLoad <= 0) {
            return null;
        }

        $vesAtIssue = round($usdTotal * $rateAtIssue, 2);
        $vesAtRegistration = round($usdTotal * $rateAtLoad, 2);

        $invoiceNumber = trim((string) ($purchase->supplier_invoice_number ?? ''));
        if ($invoiceNumber === '') {
            $invoiceNumber = (string) $purchase->purchase_number;
        }

        $supplier = $purchase->supplier;

        return [
            'usd_total' => $usdTotal,
            'issued_at' => $issuedAt,
            'registered_at' => $registeredAt,
            'rate_at_issue' => $rateAtIssue,
            'rate_at_load' => $rateAtLoad,
            'ves_at_issue' => $vesAtIssue,
            'ves_at_registration' => $vesAtRegistration,
            'invoice_number' => $invoiceNumber,
            'supplier_name' => $supplier !== null ? $supplier->displayName() : '—',
            'supplier_tax_id' => $supplier?->tax_id,
            'supplier_control_number' => $purchase->supplier_control_number,
        ];
    }

    public function documentTotalUsd(Purchase $purchase): float
    {
        $total = round((float) $purchase->total, 2);

        if ($purchase->entryCurrency() === PurchaseEntryCurrency::VES) {
            $rate = (float) ($purchase->official_usd_ves_rate ?? 0);

            return $rate > 0 ? round($total / $rate, 2) : 0.0;
        }

        return $total;
    }

    public function resolveRateForPurchase(Purchase $purchase, CarbonInterface|string|null $date): ?float
    {
        $rate = $this->rateClient->rateForDate($date);
        if ($rate !== null && $rate > 0) {
            return $rate;
        }

        if ($purchase->entryCurrency() === PurchaseEntryCurrency::VES) {
            $stored = (float) ($purchase->official_usd_ves_rate ?? 0);

            return $stored > 0 ? $stored : null;
        }

        return null;
    }
}
