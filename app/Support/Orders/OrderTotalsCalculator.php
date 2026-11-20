<?php

namespace App\Support\Orders;

use App\Models\Inventory;
use App\Models\OrderItem;
use App\Models\PartnerCompany;
use App\Models\Product;
use App\Support\Finance\DefaultVatRate;
use App\Support\Sales\ProductUnitPricingForBranch;

/**
 * Montos de línea y agregados de pedido a partir del catálogo (precio lista, descuento %, IVA opcional).
 */
final class OrderTotalsCalculator
{
    /**
     * @return array{
     *     unit_price: float,
     *     unit_cost: float|null,
     *     profit_percent: float|null,
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
        $unitCost = max(0.0, (float) ($product->cost_price ?? 0));
        $partnerProfitPercentage = max(0.0, $partnerProfitPercentage);
        $discountAmount = $product->monetaryLineDiscountForQuantity($qty);
        $storedCost = null;
        $storedPercent = null;

        if ($partnerProfitPercentage > 0) {
            $unitPriceEffective = $unitCost * (1 + ($partnerProfitPercentage / 100));
            $discountAmount = 0.0;
            $storedCost = round($unitCost, 2);
            $storedPercent = round($partnerProfitPercentage, 6);
        } else {
            $unitPriceEffective = $product->effectiveSaleUnitPrice();
        }

        $unitPriceEffective = round($unitPriceEffective, 2);
        $lineSubtotal = round($qty * $unitPriceEffective, 2);

        $rate = self::vatRatePercentForProduct($product);
        $taxAmount = $rate > 0 ? round($lineSubtotal * ($rate / 100), 2) : 0.0;
        $lineTotal = round($lineSubtotal + $taxAmount, 2);

        return [
            'unit_price' => $unitPriceEffective,
            'unit_cost' => $storedCost,
            'profit_percent' => $storedPercent,
            'discount_amount' => $discountAmount,
            'line_subtotal' => $lineSubtotal,
            'tax_amount' => $taxAmount,
            'line_total' => $lineTotal,
            'product_name_snapshot' => (string) $product->name,
            'sku_snapshot' => $product->sku,
        ];
    }

    /**
     * Precio de venta de una línea al aceptarla.
     * Si el pedido llegó sin precio, se usa el de Las Delicias (o el de lista del producto).
     *
     * @return array{unit_price_usd: float, line_total_usd: float}
     */
    public static function acceptanceSaleAmounts(OrderItem $item, ?int $branchId, ?Inventory $inventory): array
    {
        $quantity = round(max(0.0, (float) $item->quantity), 3);
        $unit = round((float) $item->unit_price, 2);
        $line = round((float) $item->line_total, 2);

        if ($unit > 0.0 || $line > 0.0) {
            if ($line <= 0.0) {
                $line = round($unit * $quantity, 2);
            }

            return [
                'unit_price_usd' => $unit,
                'line_total_usd' => $line,
            ];
        }

        $product = $item->product;

        if (! $product instanceof Product) {
            return [
                'unit_price_usd' => 0.0,
                'line_total_usd' => 0.0,
            ];
        }

        $pricing = ProductUnitPricingForBranch::resolve($product, (int) ($branchId ?? 0), $inventory);
        $unit = round(max(0.0, (float) $pricing['unit_final']), 2);

        return [
            'unit_price_usd' => $unit,
            'line_total_usd' => round($unit * $quantity, 2),
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
                $partnerProfitPercentage = $partner->medicineDispatchProfitPercent();
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
