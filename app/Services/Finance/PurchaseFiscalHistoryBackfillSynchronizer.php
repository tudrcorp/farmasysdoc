<?php

namespace App\Services\Finance;

use App\Enums\PurchaseStatus;
use App\Models\Purchase;
use App\Models\PurchaseBook;
use App\Models\PurchaseHistory;
use App\Models\PurchaseLedger;
use App\Services\Audit\AuditLogger;
use App\Support\Finance\PurchaseFiscalHistoryBackfillResult;
use App\Support\Purchases\PurchaseHistoryEntryType;
use App\Support\Purchases\PurchasePaymentStatus;
use Illuminate\Support\Facades\Auth;

/**
 * Sincroniza compras históricas hacia Histórico, Retenciones y Libro de Compras.
 * Exige % SENIAT en el proveedor antes de generar retención / comprobante.
 */
final class PurchaseFiscalHistoryBackfillSynchronizer
{
    public function __construct(
        private readonly PurchaseHistoryFromPurchaseSynchronizer $historySynchronizer,
        private readonly PurchaseBookFromPurchaseSynchronizer $retentionSynchronizer,
        private readonly PurchaseLedgerFromPurchaseSynchronizer $ledgerSynchronizer,
        private readonly PurchaseHistoryRetentionVoucherSynchronizer $historyRetentionSynchronizer,
    ) {}

    public function run(): PurchaseFiscalHistoryBackfillResult
    {
        $result = new PurchaseFiscalHistoryBackfillResult;

        $query = Purchase::query()
            ->with(['supplier'])
            ->orderBy('id');

        foreach ($query->cursor() as $purchase) {
            if (! $purchase instanceof Purchase) {
                continue;
            }

            $result->examined++;

            try {
                $this->syncOne($purchase, $result);
            } catch (\Throwable $e) {
                $result->errors++;
                $result->errorMessages[] = ($purchase->purchase_number ?? '#'.$purchase->id).': '.$e->getMessage();
                report($e);
            }
        }

        AuditLogger::record(
            event: 'purchase_fiscal_history_backfill_finished',
            description: 'Sincronización masiva de histórico / retenciones / libro de compras finalizada.',
            properties: [
                'actor' => Auth::user()?->email ?? Auth::user()?->name ?? 'sistema',
                'examined' => $result->examined,
                'histories_created' => $result->historiesCreated,
                'retentions_created' => $result->retentionsCreated,
                'ledger_rows_touched' => $result->ledgerRowsCreated,
                'histories_retention_updated' => $result->historiesRetentionUpdated,
                'skipped_missing_retention_percent' => $result->skippedMissingRetentionPercent,
                'skipped_no_vat' => $result->skippedNoVat,
                'errors' => $result->errors,
                'pending_suppliers' => array_values(array_unique($result->pendingSupplierNames)),
            ],
        );

        return $result;
    }

    private function syncOne(Purchase $purchase, PurchaseFiscalHistoryBackfillResult $result): void
    {
        $status = $purchase->status;
        if ($status === PurchaseStatus::Annulled || $status === PurchaseStatus::Cancelled) {
            $result->skippedAnnulled++;

            return;
        }

        $changed = false;

        if (($purchase->payment_status ?? '') === PurchasePaymentStatus::PAGADO_CONTADO) {
            $hadContado = PurchaseHistory::query()
                ->where('purchase_id', $purchase->id)
                ->where('entry_type', PurchaseHistoryEntryType::COMPRA_CONTADO)
                ->exists();

            $history = $this->historySynchronizer->syncFromPurchase($purchase);
            if ($history !== null && ! $hadContado) {
                $result->historiesCreated++;
                $changed = true;
            }
        }

        $taxTotal = round((float) $purchase->tax_total, 2);
        $hadRetention = PurchaseBook::query()->where('purchase_id', $purchase->id)->exists();
        $ledgerCountBefore = PurchaseLedger::query()->where('purchase_id', $purchase->id)->count();

        if ($taxTotal <= 0) {
            $this->ledgerSynchronizer->syncFromPurchase($purchase, null);
            $ledgerDelta = PurchaseLedger::query()->where('purchase_id', $purchase->id)->count() - $ledgerCountBefore;
            if ($ledgerDelta > 0) {
                $result->ledgerRowsCreated += $ledgerDelta;
                $changed = true;
            }
            $result->skippedNoVat++;
            if (! $changed) {
                $result->alreadySynced++;
            }

            return;
        }

        $purchase->loadMissing('supplier');
        if ($purchase->supplier?->seniat_retention_percent === null) {
            $result->skippedMissingRetentionPercent++;
            $supplierLabel = $purchase->supplier !== null
                ? (filled($purchase->supplier->legal_name)
                    ? (string) $purchase->supplier->legal_name
                    : $purchase->supplier->displayName())
                : ('Compra '.$purchase->purchase_number);
            $result->pendingSupplierNames[] = $supplierLabel;

            $this->ledgerSynchronizer->syncFromPurchase($purchase, null);
            $ledgerDelta = PurchaseLedger::query()->where('purchase_id', $purchase->id)->count() - $ledgerCountBefore;
            if ($ledgerDelta > 0) {
                $result->ledgerRowsCreated += $ledgerDelta;
                $changed = true;
            }

            if (! $changed) {
                $result->alreadySynced++;
            }

            return;
        }

        $retention = $this->retentionSynchronizer->syncFromPurchase($purchase);
        if ($retention === null) {
            $result->skippedMissingBcvRate++;

            return;
        }

        if (! $hadRetention) {
            $result->retentionsCreated++;
            $changed = true;
        }

        $this->ledgerSynchronizer->syncFromPurchase($purchase, $retention);
        $ledgerDelta = PurchaseLedger::query()->where('purchase_id', $purchase->id)->count() - $ledgerCountBefore;
        if ($ledgerDelta > 0) {
            $result->ledgerRowsCreated += $ledgerDelta;
            $changed = true;
        }

        $historiesNeedingUpdate = PurchaseHistory::query()
            ->where('purchase_id', $purchase->id)
            ->where(function ($query) use ($retention): void {
                $query->whereNull('retention_voucher_number')
                    ->orWhere('retention_voucher_number', '!=', $retention->voucher_number)
                    ->orWhereNull('retention_amount_ves')
                    ->orWhere('retention_amount_ves', '!=', $retention->tax_retained_ves);
            })
            ->count();

        $historiesUpdated = $this->historyRetentionSynchronizer->syncFromPurchaseBook($retention);
        if ($historiesNeedingUpdate > 0 && $historiesUpdated > 0) {
            $result->historiesRetentionUpdated += $historiesUpdated;
            $changed = true;
        }

        if (! $changed) {
            $result->alreadySynced++;
        }
    }
}
