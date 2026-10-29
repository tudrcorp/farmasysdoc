<?php

namespace App\Support\Fiscal;

/**
 * Montos en Bs calculados exactamente como la máquina fiscal: cada ítem se convierte a Bs **sin IVA**
 * (precio y descuento redondeados a céntimos) y el IVA se aplica una sola vez, en Bs, sobre la base
 * acumulada de cada alícuota.
 *
 * Es la única regla para pasar una venta en USD a Bs: la caja, el cobro en bolívares y el payload de
 * la factura fiscal la usan, de modo que lo cobrado coincide con lo impreso y con el reporte Z.
 */
final class FiscalVesAmounts
{
    public const TAX_CODE_GENERAL = 'G';

    public const TAX_CODE_EXEMPT = 'E';

    /**
     * @return array{quantity: float, unit_price_ves: float, discount_ves: float, tax_code: string, tax_rate_percent: float}
     */
    public static function item(float $quantity, float $unitPriceUsd, float $discountUsd, bool $isTaxed, float $vatRatePercent, float $vesPerUsd): array
    {
        $isTaxed = $isTaxed && $vatRatePercent > 0.0;

        return [
            'quantity' => round($quantity, 3),
            'unit_price_ves' => round($unitPriceUsd * $vesPerUsd, 2),
            'discount_ves' => round(max(0.0, $discountUsd) * $vesPerUsd, 2),
            'tax_code' => $isTaxed ? self::TAX_CODE_GENERAL : self::TAX_CODE_EXEMPT,
            'tax_rate_percent' => $isTaxed ? $vatRatePercent : 0.0,
        ];
    }

    /**
     * Precio unitario con IVA en Bs tal como sale en la factura fiscal para una unidad.
     */
    public static function unitPriceWithVatVes(float $unitPriceUsd, bool $isTaxed, float $vatRatePercent, float $vesPerUsd): float
    {
        return self::lineTotalVes(self::item(1.0, $unitPriceUsd, 0.0, $isTaxed, $vatRatePercent, $vesPerUsd));
    }

    /**
     * Total de una línea en Bs (base − descuento + IVA de esa línea), para mostrar en la caja.
     *
     * @param  array{quantity: float, unit_price_ves: float, discount_ves: float, tax_code: string, tax_rate_percent: float}  $item
     */
    public static function lineTotalVes(array $item): float
    {
        $totals = self::totals([$item]);

        return $totals['total_ves'];
    }

    /**
     * @param  list<array{quantity: float, unit_price_ves: float, discount_ves: float, tax_code: string, tax_rate_percent: float}>  $items
     * @return array{subtotal_ves: float, discount_ves: float, tax_ves: float, total_ves: float}
     */
    public static function totals(array $items): array
    {
        $subtotal = 0.0;
        $discount = 0.0;
        $taxableBaseByRate = [];

        foreach ($items as $item) {
            $lineGross = round((float) $item['quantity'] * (float) $item['unit_price_ves'], 2);
            $lineDiscount = round(max(0.0, (float) $item['discount_ves']), 2);

            $subtotal += $lineGross;
            $discount += $lineDiscount;

            if ($item['tax_code'] !== self::TAX_CODE_EXEMPT && (float) $item['tax_rate_percent'] > 0) {
                $rateKey = number_format((float) $item['tax_rate_percent'], 2, '.', '');
                $taxableBaseByRate[$rateKey] = ($taxableBaseByRate[$rateKey] ?? 0.0) + $lineGross - $lineDiscount;
            }
        }

        $tax = 0.0;

        foreach ($taxableBaseByRate as $ratePercent => $base) {
            $tax += round(round($base, 2) * (float) $ratePercent / 100, 2);
        }

        $subtotal = round($subtotal, 2);
        $discount = round($discount, 2);
        $tax = round($tax, 2);

        return [
            'subtotal_ves' => $subtotal,
            'discount_ves' => $discount,
            'tax_ves' => $tax,
            'total_ves' => round($subtotal - $discount + $tax, 2),
        ];
    }
}
