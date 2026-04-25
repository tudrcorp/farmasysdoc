<?php

namespace App\Support\Finance;

use App\Models\AccountsPayable;
use App\Services\Finance\VenezuelaOfficialUsdVesRateClient;
use Illuminate\Support\Collection;

/**
 * Resumen de filas y totales para el pago masivo de cuentas por pagar (tasa BCV del día actual).
 */
final class AccountsPayableBulkPaymentPayload
{
    /**
     * @param  list<array{id: int, purchase_number: string, supplier_name: string, supplier_invoice_number: string, usd: float, ves: float}>  $lines
     */
    private function __construct(
        public readonly bool $ok,
        public readonly ?string $error,
        public readonly array $lines,
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

        $rate = app(VenezuelaOfficialUsdVesRateClient::class)->rateForDate(now()->startOfDay());
        if ($rate === null || $rate <= 0) {
            return new self(false, 'No hay tasa BCV oficial (promedio) para el día actual; no se puede armar el pago masivo.', [], 0.0, 0.0, 0.0);
        }

        $rate = (float) $rate;
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
                    $rate,
                );
            }

            $record->loadMissing('purchase');
            $usd = round((float) ($record->remaining_principal_usd ?? $record->purchase_total_usd), 2);
            if ($usd <= 0) {
                return new self(
                    false,
                    'La cuenta por pagar #'.$record->getKey().' no tiene principal pendiente en USD; no puede incluirse en el pago masivo.',
                    [],
                    0.0,
                    0.0,
                    $rate,
                );
            }

            $ves = round($usd * $rate, 2);
            $lines[] = [
                'id' => (int) $record->getKey(),
                'purchase_number' => (string) ($record->purchase?->purchase_number ?? '—'),
                'supplier_name' => (string) $record->supplier_name,
                'supplier_invoice_number' => (string) $record->supplier_invoice_number,
                'usd' => $usd,
                'ves' => $ves,
            ];
            $totalUsd += $usd;
            $totalVes += $ves;
        }

        if ($lines === []) {
            return new self(false, 'No hay filas válidas en la selección.', [], 0.0, 0.0, $rate);
        }

        $totalUsd = round($totalUsd, 2);
        $totalVes = round($totalVes, 2);

        return new self(true, null, $lines, $totalUsd, $totalVes, $rate);
    }
}
