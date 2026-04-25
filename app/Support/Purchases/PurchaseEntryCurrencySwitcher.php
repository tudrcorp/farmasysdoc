<?php

namespace App\Support\Purchases;

use App\Enums\PurchaseEntryCurrency;
use App\Services\Finance\VenezuelaOfficialUsdVesRateClient;

/**
 * Ajusta costos de línea al cambiar la moneda del documento (VES ↔ USD) usando la tasa BCV de la fecha de factura.
 */
final class PurchaseEntryCurrencySwitcher
{
    private static function money(float $value): float
    {
        $rounded = round($value, 2);

        return $rounded == 0.0 ? 0.0 : $rounded;
    }

    /**
     * @param  array<string, mixed>  $formData  Copia del estado del formulario (p. ej. $livewire->data).
     * @return array{0: list<array<string, mixed>>, 1: array<string, mixed>}|null [items, header] o null si no aplica.
     */
    public static function computeAdjustedItemsAndHeader(array $formData, string $previousCode, string $newCode): ?array
    {
        if ($previousCode === $newCode) {
            return null;
        }

        $items = $formData['items'] ?? [];
        if (! is_array($items) || $items === []) {
            return null;
        }

        $rate = app(VenezuelaOfficialUsdVesRateClient::class)
            ->rateForDate($formData['supplier_invoice_date'] ?? null);
        if ($rate === null || $rate <= 0) {
            return null;
        }

        $rate = (float) $rate;

        foreach ($items as $i => $row) {
            if (! is_array($row)) {
                continue;
            }

            $uc = self::money(max(0.0, (float) ($row['unit_cost'] ?? 0)));

            if ($previousCode === PurchaseEntryCurrency::VES->value && $newCode === PurchaseEntryCurrency::USD->value) {
                $uc = self::money($uc / $rate);
            } elseif ($previousCode === PurchaseEntryCurrency::USD->value && $newCode === PurchaseEntryCurrency::VES->value) {
                $uc = self::money($uc * $rate);
            } else {
                continue;
            }

            $row['unit_cost'] = $uc;
            $amounts = PurchaseDocumentTotals::lineAmounts([
                'quantity_ordered' => $row['quantity_ordered'] ?? 1,
                'unit_cost' => $uc,
                'line_discount_percent' => $row['line_discount_percent'] ?? 0,
                'line_vat_percent' => $row['line_vat_percent'] ?? 0,
            ]);
            $row['line_subtotal'] = $amounts['line_subtotal'];
            $row['tax_amount'] = $amounts['tax_amount'];
            $row['line_total'] = $amounts['line_total'];
            $items[$i] = $row;
        }

        $docDisc = (float) ($formData['document_discount_percent'] ?? 0);
        $header = PurchaseDocumentTotals::documentHeaderWithDocumentDiscount($items, $docDisc);

        return [$items, $header];
    }
}
