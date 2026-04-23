<?php

namespace App\Services\Finance;

use App\Models\AccountsPayable;
use App\Models\Purchase;
use App\Services\Audit\AuditLogger;
use App\Support\Finance\AccountsPayableStatus;
use App\Support\Purchases\PurchasePaymentStatus;
use Illuminate\Support\Carbon;

/**
 * Crea una fila de cuentas por pagar cuando la compra queda a crédito.
 */
final class AccountsPayableFromPurchaseSynchronizer
{
    public function __construct(
        private readonly PurchaseMonetarySnapshotBuilder $snapshotBuilder,
        private readonly VenezuelaOfficialUsdVesRateClient $rateClient,
    ) {}

    public function syncFromPurchase(Purchase $purchase): ?AccountsPayable
    {
        if (($purchase->payment_status ?? '') !== PurchasePaymentStatus::A_CREDITO) {
            return null;
        }

        $existing = AccountsPayable::query()->where('purchase_id', $purchase->id)->first();
        if ($existing !== null) {
            return $existing;
        }

        $snapshot = $this->snapshotBuilder->build($purchase);
        if ($snapshot === null) {
            $usdTotal = $this->snapshotBuilder->documentTotalUsd($purchase);
            if ($usdTotal <= 0) {
                AuditLogger::record(
                    event: 'accounts_payable_skipped_zero_usd_total',
                    description: 'Cuentas por pagar: no se generó registro porque el total en USD equivalente es cero o inválido.',
                    auditableType: Purchase::class,
                    auditableId: (string) $purchase->getKey(),
                    auditableLabel: $purchase->purchase_number,
                    properties: [
                        'purchase_id' => $purchase->id,
                        'payment_status' => $purchase->payment_status,
                        'entry_currency' => $purchase->entry_currency?->value ?? (string) $purchase->entry_currency,
                        'total' => $purchase->total,
                        'official_usd_ves_rate' => $purchase->official_usd_ves_rate,
                    ],
                );
            } else {
                $issuedAt = Carbon::parse($purchase->supplier_invoice_date ?? $purchase->registered_in_system_date ?? now())->startOfDay();
                $registeredAt = Carbon::parse($purchase->registered_in_system_date ?? $issuedAt)->startOfDay();
                AuditLogger::record(
                    event: 'accounts_payable_skipped_missing_bcv_rate',
                    description: 'Cuentas por pagar: no se generó registro por falta de tasa BCV (promedio oficial) para fechas de emisión o de carga.',
                    auditableType: Purchase::class,
                    auditableId: (string) $purchase->getKey(),
                    auditableLabel: $purchase->purchase_number,
                    properties: [
                        'purchase_id' => $purchase->id,
                        'issued_date' => $issuedAt->toDateString(),
                        'registered_date' => $registeredAt->toDateString(),
                    ],
                );
            }

            return null;
        }

        $usdTotal = $snapshot['usd_total'];
        $issuedAt = $snapshot['issued_at'];
        $registeredAt = $snapshot['registered_at'];
        $rateAtIssue = $snapshot['rate_at_issue'];
        $rateAtLoad = $snapshot['rate_at_load'];
        $vesAtIssue = $snapshot['ves_at_issue'];
        $originalBalance = $snapshot['ves_at_registration'];

        $todayRate = $this->rateClient->rateForDate(now());
        $currentBalance = ($todayRate !== null && $todayRate > 0)
            ? round($usdTotal * $todayRate, 2)
            : $originalBalance;

        $dueAt = filled($purchase->payment_due_date)
            ? Carbon::parse($purchase->payment_due_date)->startOfDay()
            : $issuedAt->copy()->addDays(30);

        $accountsPayable = AccountsPayable::query()->create([
            'purchase_id' => $purchase->id,
            'branch_id' => $purchase->branch_id,
            'status' => AccountsPayableStatus::POR_PAGAR,
            'issued_at' => $issuedAt->toDateString(),
            'due_at' => $dueAt->toDateString(),
            'supplier_invoice_number' => $snapshot['invoice_number'],
            'supplier_control_number' => $snapshot['supplier_control_number'],
            'supplier_tax_id' => $snapshot['supplier_tax_id'],
            'supplier_name' => $snapshot['supplier_name'],
            'purchase_total_usd' => $usdTotal,
            'remaining_principal_usd' => $usdTotal,
            'purchase_total_ves_at_issue' => $vesAtIssue,
            'original_balance_ves' => $originalBalance,
            'current_balance_ves' => $currentBalance,
            'last_balance_recalculated_at' => ($todayRate !== null && $todayRate > 0) ? now() : null,
            'notes' => null,
        ]);

        AuditLogger::forModel(
            $accountsPayable,
            'accounts_payable_created_from_purchase',
            [
                'purchase_id' => $purchase->id,
                'purchase_number' => $purchase->purchase_number,
                'payment_due_date' => $purchase->payment_due_date?->toDateString(),
                'due_at_applied' => $dueAt->toDateString(),
                'bcv_rate_at_issue' => $rateAtIssue,
                'bcv_rate_at_load' => $rateAtLoad,
                'bcv_rate_at_creation_for_current' => $todayRate,
            ],
        );

        return $accountsPayable;
    }
}
