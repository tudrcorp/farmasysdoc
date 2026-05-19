<?php

namespace App\Support\Orders;

use App\Models\PartnerCompany;
use App\Models\Product;
use App\Support\Finance\DefaultVatRate;

/**
 * Montos de línea y agregados de pedido a partir del catálogo (precio lista, descuento %, IVA opcional).
 */
final class OrderTotalsCalculator
{
    /**
     * @return array{
     *     unit_price: float,
     *     discount_amount: float,
     *     line_subtotal: float,
     *     tax_amount: float,
     *     line_total: float,
     *     product_name_snapshot: string,
     *     sku_snapshot: string|null,
     * }
     */
    public static function lineAmounts(Product $product, float $quantity, float $partnerProfitPercentage = 0.0): array
    {
        $qty = max(0.001, $quantity);
        $unitPriceEffective = $product->effectiveSaleUnitPrice();
        $partnerProfitPercentage = max(0.0, $partnerProfitPercentage);
        if ($partnerProfitPercentage > 0) {
            $unitPriceEffective *= (1 + ($partnerProfitPercentage / 100));
        }
        $unitPriceEffective = round($unitPriceEffective, 2);
        $discountAmount = $product->monetaryLineDiscountForQuantity($qty);
        $lineSubtotal = round($qty * $unitPriceEffective, 2);

        $rate = self::vatRatePercentForProduct($product);
        $taxAmount = $rate > 0 ? round($lineSubtotal * ($rate / 100), 2) : 0.0;
        $lineTotal = round($lineSubtotal + $taxAmount, 2);

        return [
            'unit_price' => $unitPriceEffective,
            'discount_amount' => $discountAmount,
            'line_subtotal' => $lineSubtotal,
            'tax_amount' => $taxAmount,
            'line_total' => $lineTotal,
            'product_name_snapshot' => (string) $product->name,
            'sku_snapshot' => $product->sku,
        ];
    }

    /**
     * @param  array<int, array<string, mixed>>  $itemStates  Filas del repetidor (product_id, quantity).
     * @return array{subtotal: float, tax_total: float, discount_total: float, total: float}
     */
    public static function aggregateFromItemStates(array $itemStates, ?int $partnerCompanyId = null): array
    {
        $subtotalNet = 0.0;
        $taxTotal = 0.0;
        $discountTotal = 0.0;
        $total = 0.0;
        $partnerProfitPercentage = 0.0;
        $partnerOrderDiscountPercentage = 0.0;

        if ($partnerCompanyId !== null && $partnerCompanyId > 0) {
            $partner = PartnerCompany::query()->find($partnerCompanyId);
            if ($partner instanceof PartnerCompany) {
                $partnerProfitPercentage = max(0.0, (float) ($partner->profit_percentage_a ?? 0));
                $partnerOrderDiscountPercentage = max(0.0, (float) ($partner->discount_percentage ?? 0));
            }
        }

        foreach ($itemStates as $row) {
            $productId = $row['product_id'] ?? null;
            if ($productId === null || $productId === '') {
                continue;
            }

            $product = Product::query()->find((int) $productId);
            if ($product === null) {
                continue;
            }

            $qty = max(0.001, (float) ($row['quantity'] ?? 1));
            $line = self::lineAmounts($product, $qty, $partnerProfitPercentage);
            $subtotalNet += $line['line_subtotal'];
            $taxTotal += $line['tax_amount'];
            $discountTotal += $line['discount_amount'];
            $total += $line['line_total'];
        }

        $partnerOrderDiscountAmount = $partnerOrderDiscountPercentage > 0
            ? round($total * ($partnerOrderDiscountPercentage / 100), 2)
            : 0.0;
        $discountTotal += $partnerOrderDiscountAmount;
        $total = max(0.0, $total - $partnerOrderDiscountAmount);

        $subtotalWithVat = $subtotalNet + $taxTotal;

        return [
            'subtotal' => round($subtotalWithVat, 2),
            'tax_total' => round($taxTotal, 2),
            'discount_total' => round($discountTotal, 2),
            'total' => round($total, 2),
        ];
    }

    private static function vatRatePercentForProduct(Product $product): float
    {
        if (! (bool) $product->applies_vat) {
            return 0.0;
        }

        $rate = DefaultVatRate::percent();

        return max(0.0, min(100.0, $rate));
    }
}
