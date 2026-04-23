<?php

namespace App\Services\Finance;

use App\Models\Purchase;
use App\Models\PurchaseHistory;
use App\Services\Audit\AuditLogger;
use App\Support\Purchases\PurchaseHistoryEntryType;
use App\Support\Purchases\PurchasePaymentStatus;
use Illuminate\Support\Facades\Auth;

/**
 * Registra en histórico las compras pagadas de contado al confirmarse.
 */
final class PurchaseHistoryFromPurchaseSynchronizer
{
    public function __construct(
        private readonly PurchaseMonetarySnapshotBuilder $snapshotBuilder,
    ) {}

    public function syncFromPurchase(Purchase $purchase): ?PurchaseHistory
    {
        if (($purchase->payment_status ?? '') !== PurchasePaymentStatus::PAGADO_CONTADO) {
            return null;
        }

        $exists = PurchaseHistory::query()
            ->where('purchase_id', $purchase->id)
            ->where('entry_type', PurchaseHistoryEntryType::COMPRA_CONTADO)
            ->exists();
        if ($exists) {
            return PurchaseHistory::query()
                ->where('purchase_id', $purchase->id)
                ->where('entry_type', PurchaseHistoryEntryType::COMPRA_CONTADO)
                ->firstOrFail();
        }

        $snapshot = $this->snapshotBuilder->build($purchase);
        if ($snapshot === null) {
            AuditLogger::record(
                event: 'purchase_history_skipped_contado',
                description: 'Histórico de compras: no se generó fila de contado (totales o tasas BCV incompletos).',
                auditableType: Purchase::class,
                auditableId: (string) $purchase->getKey(),
                auditableLabel: $purchase->purchase_number,
                properties: [
                    'payment_status' => $purchase->payment_status,
                    'reason' => 'snapshot_unavailable',
                ],
            );

            return null;
        }

        $actor = Auth::user()?->email
            ?? Auth::user()?->name
            ?? 'sistema';

        $history = PurchaseHistory::query()->create([
            'entry_type' => PurchaseHistoryEntryType::COMPRA_CONTADO,
            'purchase_id' => $purchase->id,
            'branch_id' => $purchase->branch_id,
            'accounts_payable_id' => null,
            'issued_at' => $snapshot['issued_at']->toDateString(),
            'registered_in_system_date' => $snapshot['registered_at']->toDateString(),
            'supplier_invoice_number' => $snapshot['invoice_number'],
            'supplier_control_number' => $snapshot['supplier_control_number'],
            'supplier_tax_id' => $snapshot['supplier_tax_id'],
            'supplier_name' => $snapshot['supplier_name'],
            'purchase_total_usd' => $snapshot['usd_total'],
            'purchase_total_ves_at_issue' => $snapshot['ves_at_issue'],
            'total_ves_at_system_registration' => $snapshot['ves_at_registration'],
            'payment_method' => null,
            'payment_form' => null,
            'paid_at' => null,
            'amount_paid_ves' => null,
            'amount_paid_usd' => null,
            'bcv_rate_at_payment' => null,
            'notes' => null,
            'created_by' => $actor,
        ]);

        AuditLogger::forModel(
            $history,
            'purchase_history_compra_contado_registered',
            [
                'origen' => 'sistema_tras_guardar_compra',
                'purchase_id' => $purchase->getKey(),
                'purchase_number' => $purchase->purchase_number,
                'payment_status' => $purchase->payment_status,
                'entry_type' => PurchaseHistoryEntryType::COMPRA_CONTADO,
                'purchase_total_usd' => $snapshot['usd_total'],
                'purchase_total_ves_at_issue' => $snapshot['ves_at_issue'],
                'total_ves_at_system_registration' => $snapshot['ves_at_registration'],
                'bcv_rate_at_issue' => $snapshot['rate_at_issue'],
                'bcv_rate_at_registration' => $snapshot['rate_at_load'],
            ],
        );

        AuditLogger::forModel(
            $purchase,
            'purchase_compra_contado_historic_written',
            [
                'origen' => 'sistema_tras_guardar_compra',
                'purchase_history_id' => $history->getKey(),
                'entry_type' => PurchaseHistoryEntryType::COMPRA_CONTADO,
            ],
        );

        return $history;
    }
}
