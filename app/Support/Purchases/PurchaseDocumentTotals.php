<?php

namespace App\Support\Purchases;

use App\Support\Finance\DefaultVatRate;

/**
 * Cálculo de montos por línea y totales del documento de compra (formulario, validación e informes).
 *
 * Orden por línea: (1) subtotal bruto = cantidad × costo; (2) descuento % sobre el bruto; (3) base neta;
 * (4) IVA % solo sobre la base neta (si la línea lleva tasa).
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
        $tax = $vat > 0 ? round($lineSub * ($vat / 100), 2) : 0.0;
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

    /**
     * Totales de encabezado: subtotales por tipo de línea, descuento global % sobre la sumatoria,
     * bases netas tras ese descuento e IVA sobre la base gravada (tasa global del sistema).
     *
     * @param  array<int, mixed>  $items  Filas con keys de línea de compra (quantity_ordered, unit_cost, line_discount_percent, line_vat_percent).
     * @return array{
     *     subtotal_exempt_amount: float,
     *     subtotal_taxable_amount: float,
     *     subtotal: float,
     *     discount_total: float,
     *     document_discount_percent: float,
     *     document_discount_amount: float,
     *     net_exempt_after_document_discount: float,
     *     net_taxable_after_document_discount: float,
     *     tax_total: float,
     *     total: float
     * }
     */
    public static function documentHeaderWithDocumentDiscount(array $items, float $documentDiscountPercent): array
    {
        $subExempt = 0.0;
        $subTaxable = 0.0;
        $sumLineDisc = 0.0;

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }

            $amounts = self::lineAmounts($item);
            $vat = (float) ($item['line_vat_percent'] ?? 0);
            if ($vat > 0.00001) {
                $subTaxable += $amounts['line_subtotal'];
            } else {
                $subExempt += $amounts['line_subtotal'];
            }

            $qty = max(0.0, (float) ($item['quantity_ordered'] ?? 0));
            $cost = max(0.0, (float) ($item['unit_cost'] ?? 0));
            $disc = min(100.0, max(0.0, (float) ($item['line_discount_percent'] ?? 0)));
            $base = $qty * $cost;
            $sumLineDisc += round($base * ($disc / 100), 2);
        }

        $subExempt = round($subExempt, 2);
        $subTaxable = round($subTaxable, 2);
        $subtotal = round($subExempt + $subTaxable, 2);
        $discountTotal = round($sumLineDisc, 2);

        $dPct = min(100.0, max(0.0, $documentDiscountPercent));
        $factor = 1 - ($dPct / 100);

        $netExempt = round($subExempt * $factor, 2);
        $netTaxable = round($subTaxable * $factor, 2);
        $docDiscAmount = round($subtotal - $netExempt - $netTaxable, 2);

        $rate = DefaultVatRate::percent();
        $taxTotal = $netTaxable > 0 && $rate > 0
            ? round($netTaxable * ($rate / 100), 2)
            : 0.0;

        $total = round($netExempt + $netTaxable + $taxTotal, 2);

        return [
            'subtotal_exempt_amount' => $subExempt,
            'subtotal_taxable_amount' => $subTaxable,
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'document_discount_percent' => round($dPct, 2),
            'document_discount_amount' => $docDiscAmount,
            'net_exempt_after_document_discount' => $netExempt,
            'net_taxable_after_document_discount' => $netTaxable,
            'tax_total' => $taxTotal,
            'total' => $total,
        ];
    }
}
