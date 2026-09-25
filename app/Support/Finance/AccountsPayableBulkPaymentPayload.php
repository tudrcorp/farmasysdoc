<?php

namespace App\Support\Finance;

use App\Models\AccountsPayable;
use Illuminate\Support\Collection;

/**
 * Resumen de filas y totales para el pago masivo de cuentas por pagar.
 * El monto en Bs es el mismo «Total a pagar» del listado.
 */
final class AccountsPayableBulkPaymentPayload
{
    /**
     * @param  list<array<string, mixed>>  $selectedLines
     */
    private function __construct(
        public readonly bool $ok,
        public readonly ?string $error,
        public readonly array $selectedLines,
        public readonly float $totalUsd,
        public readonly float $totalVes,
        public readonly float $rate,
    ) {}

    /**
     * @param  Collection<int, AccountsPayable>  $records
     */
    public static function fromSelection(Collection $records): self
    {
        if ($records->isEmpty()) {
            return new self(false, 'No seleccionó ninguna cuenta por pagar.', [], 0.0, 0.0, 0.0);
        }

        $lines = [];
        $totalUsd = 0.0;
        $totalVes = 0.0;

        foreach ($records as $record) {
            if (! $record instanceof AccountsPayable) {
                continue;
            }
            if (($record->status ?? '') !== AccountsPayableStatus::POR_PAGAR) {
                return new self(
                    false,
                    'Solo puede incluir cuentas en estado «Por pagar». Quite de la selección la fila #'.$record->getKey().' o las que ya estén pagadas/anuladas.',
                    [],
                    0.0,
                    0.0,
                    0.0,
                );
            }

            $record->loadMissing(['purchase', 'branch', 'purchase.purchaseBook', 'purchase.supplier']);
            $usd = round((float) ($record->remaining_principal_usd ?? $record->purchase_total_usd), 2);
            if ($usd <= 0) {
                return new self(
                    false,
                    'La cuenta por pagar #'.$record->getKey().' no tiene principal pendiente en USD; no puede incluirse en el pago masivo.',
                    [],
                    0.0,
                    0.0,
                    0.0,
                );
            }

            $ves = AccountsPayableInvoiceTaxSnapshot::amountPayableVes($record);
            if ($ves <= 0) {
                return new self(
                    false,
                    'La cuenta por pagar #'.$record->getKey().' no tiene total a pagar en bolívares; no puede incluirse en el pago masivo.',
                    [],
                    0.0,
                    0.0,
                    0.0,
                );
            }

            $lines[] = self::lineStateForRepeater($record, $ves);
            $totalUsd += $usd;
            $totalVes += $ves;
        }

        if ($lines === []) {
            return new self(false, 'No hay filas válidas en la selección.', [], 0.0, 0.0, 0.0);
        }

        $totalUsd = round($totalUsd, 2);
        $totalVes = round($totalVes, 2);

        return new self(true, null, $lines, $totalUsd, $totalVes, 0.0);
    }

    /**
     * @return array<string, mixed>
     */
    private static function lineStateForRepeater(AccountsPayable $record, float $ves): array
    {
        $supplier = trim((string) $record->supplier_name);
        $invoice = trim((string) $record->supplier_invoice_number);
        $rif = trim((string) ($record->supplier_tax_id ?? ''));
        $snapshot = AccountsPayableInvoiceTaxSnapshot::for($record);
        $registrationRate = AccountsPayableInvoiceTaxSnapshot::purchaseRegistrationBcvRate($record);
        $invoiceTotalVes = (float) $record->purchase_total_ves_at_issue;

        return [
            'accounts_payable_id' => (int) $record->getKey(),
            'supplier_name' => $supplier !== '' ? $supplier : '—',
            'invoice_number' => $invoice !== '' ? $invoice : '—',
            'rif' => $rif !== '' ? $rif : '—',
            'purchase_number' => (string) ($record->purchase?->purchase_number ?? '—'),
            'branch_name' => (string) ($record->branch?->name ?? '—'),
            'issued_at_label' => $record->issued_at?->format('d/m/Y') ?? '—',
            'due_at_label' => $record->due_at?->format('d/m/Y') ?? '—',
            'bcv_rate_label' => $registrationRate !== null && $registrationRate > 0
                ? number_format($registrationRate, 4, ',', '.').' Bs/USD'
                : '—',
            'amount_usd_label' => self::formatUsd((float) $record->purchase_total_usd),
            'invoice_total_ves_label' => self::formatBs($invoiceTotalVes),
            'tax_caused_label' => $snapshot->taxCausedVes !== null ? self::formatBs((float) $snapshot->taxCausedVes) : '—',
            'retention_percent_label' => $snapshot->retentionPercent !== null
                ? number_format($snapshot->retentionPercent, 0, ',', '.').'%'
                : '—',
            'tax_retained_label' => self::retainedLabel($snapshot),
            'amount_ves_label' => self::formatBs($ves),
        ];
    }

    private static function retainedLabel(AccountsPayableInvoiceTaxSnapshot $snapshot): string
    {
        if ($snapshot->taxRetainedVes === null) {
            return $snapshot->purchaseId === null ? '—' : 'Sin retención';
        }

        return self::formatBs((float) $snapshot->taxRetainedVes);
    }

    private static function formatUsd(float $amount): string
    {
        return number_format($amount, 2, ',', '.').' USD';
    }

    private static function formatBs(float $amount): string
    {
        return 'Bs '.number_format($amount, 2, ',', '.');
    }
}
