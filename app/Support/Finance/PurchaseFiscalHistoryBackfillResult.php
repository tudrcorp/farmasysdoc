<?php

namespace App\Support\Finance;

/**
 * Resultado de la sincronización masiva de histórico / retenciones / libro de compras.
 */
final class PurchaseFiscalHistoryBackfillResult
{
    /**
     * @param  list<string>  $pendingSupplierNames
     * @param  list<string>  $errorMessages
     */
    public function __construct(
        public int $examined = 0,
        public int $historiesCreated = 0,
        public int $retentionsCreated = 0,
        public int $ledgerRowsCreated = 0,
        public int $historiesRetentionUpdated = 0,
        public int $skippedNoVat = 0,
        public int $skippedAnnulled = 0,
        public int $skippedMissingRetentionPercent = 0,
        public int $skippedMissingBcvRate = 0,
        public int $alreadySynced = 0,
        public int $errors = 0,
        public array $pendingSupplierNames = [],
        public array $errorMessages = [],
    ) {}

    public function summaryBody(): string
    {
        $lines = [
            "Compras revisadas: {$this->examined}.",
            "Históricos contado creados: {$this->historiesCreated}.",
            "Retenciones creadas: {$this->retentionsCreated}.",
            "Filas Libro de Compras creadas/actualizadas: {$this->ledgerRowsCreated}.",
            "Históricos con datos de retención actualizados: {$this->historiesRetentionUpdated}.",
            "Ya sincronizadas (sin cambios): {$this->alreadySynced}.",
            "Omitidas sin IVA: {$this->skippedNoVat}.",
            "Omitidas anuladas/canceladas: {$this->skippedAnnulled}.",
            "Omitidas por falta de tasa BCV: {$this->skippedMissingBcvRate}.",
            "Pendientes por % SENIAT del proveedor: {$this->skippedMissingRetentionPercent}.",
            "Errores: {$this->errors}.",
        ];

        if ($this->pendingSupplierNames !== []) {
            $names = collect($this->pendingSupplierNames)->unique()->sort()->values()->take(15)->implode(', ');
            $extra = count($this->pendingSupplierNames) > 15 ? '…' : '';
            $lines[] = 'Proveedores sin % de retención: '.$names.$extra.'.';
        }

        if ($this->errorMessages !== []) {
            $lines[] = 'Detalle errores: '.collect($this->errorMessages)->take(5)->implode(' | ');
        }

        return implode("\n", $lines);
    }
}
