<?php

namespace App\Support\Purchases;

/**
 * Cálculo de montos por línea y totales del documento de compra (formulario, validación e informes).
 */
final class PurchaseDocumentTotals
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{line_subtotal: float, tax_amount: float, line_total: float}
     */
    public static function lineAmounts(array $data): array
    {
        $qty = max(0.0, (float) ($data['quantity_ordered'] ?? 0));
        $cost = max(0.0, (float) ($data['unit_cost'] ?? 0));
        $disc = min(100.0, max(0.0, (float) ($data['line_discount_percent'] ?? 0)));
        $vat = min(100.0, max(0.0, (float) ($data['line_vat_percent'] ?? 0)));
        $base = $qty * $cost;
        $afterDisc = $base * (1 - $disc / 100);
        $lineSub = round($afterDisc, 2);
        $tax = round($lineSub * ($vat / 100), 2);
        $total = round($lineSub + $tax, 2);

        return [
            'line_subtotal' => $lineSub,
            'tax_amount' => $tax,
            'line_total' => $total,
        ];
    }

    /**
     * @param  array<int, mixed>  $items
     * @return array{subtotal: float, tax_total: float, discount_total: float, total: float}
     */
    public static function documentTotals(array $items): array
    {
        $sumSub = 0.0;
        $sumTax = 0.0;
        $sumDisc = 0.0;
        $sumTotal = 0.0;

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $qty = max(0.0, (float) ($item['quantity_ordered'] ?? 0));
            $cost = max(0.0, (float) ($item['unit_cost'] ?? 0));
            $disc = min(100.0, max(0.0, (float) ($item['line_discount_percent'] ?? 0)));
            $base = $qty * $cost;
            $sumDisc += round($base * ($disc / 100), 2);

            $amounts = self::lineAmounts($item);
            $sumSub += $amounts['line_subtotal'];
            $sumTax += $amounts['tax_amount'];
            $sumTotal += $amounts['line_total'];
        }

        return [
            'subtotal' => round($sumSub, 2),
            'tax_total' => round($sumTax, 2),
            'discount_total' => round($sumDisc, 2),
            'total' => round($sumTotal, 2),
        ];
    }
}
