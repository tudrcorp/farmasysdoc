<?php

namespace App\Support\Sales;

use App\Models\User;

/**
 * Decide si una venta de caja necesita OTP de descuento y calcula su huella.
 *
 * Solo cuentan los descuentos manuales (sobre el total o por producto) que el usuario tiene permiso de aplicar;
 * el descuento comercial automático del cliente no pide OTP. La huella ata la OTP al carrito y a los
 * porcentajes exactos: si cambian, la OTP deja de servir.
 */
final class PosDiscountOtpRequirement
{
    /**
     * @param  array<mixed>  $lineItems  Filas del repeater de la caja (product_id, quantity, line_discount_percent).
     * @return array{client_id: int|null, sale_percent: float|null, lines: list<array{product_id: int, quantity: float, percent: float|null}>}|null
     */
    public static function manualDiscounts(array $lineItems, mixed $saleDiscountInput, mixed $clientId, ?User $user): ?array
    {
        $sale = PosManualDiscount::parsePercent($saleDiscountInput, PosManualDiscount::userCanDiscountSaleTotal($user));
        $salePercent = $sale['applied'] && ! $sale['invalid'] ? $sale['percent'] : null;
        $canDiscountLines = PosManualDiscount::userCanDiscountLines($user);

        $lines = [];
        $hasLineDiscount = false;

        foreach ($lineItems as $row) {
            if (! is_array($row) || ! filled($row['product_id'] ?? null) || (float) ($row['quantity'] ?? 0) <= 0) {
                continue;
            }

            $line = PosManualDiscount::parsePercent($row['line_discount_percent'] ?? null, $canDiscountLines);
            $linePercent = $line['applied'] && ! $line['invalid'] ? $line['percent'] : null;
            $hasLineDiscount = $hasLineDiscount || $linePercent !== null;

            $lines[] = [
                'product_id' => (int) $row['product_id'],
                'quantity' => round((float) $row['quantity'], 3),
                'percent' => $linePercent,
            ];
        }

        if ($lines === [] || ($salePercent === null && ! $hasLineDiscount)) {
            return null;
        }

        usort($lines, fn (array $a, array $b): int => [$a['product_id'], $a['quantity'], $a['percent']] <=> [$b['product_id'], $b['quantity'], $b['percent']]);

        return [
            'client_id' => filled($clientId) ? (int) $clientId : null,
            'sale_percent' => $salePercent,
            'lines' => $lines,
        ];
    }

    /**
     * @param  array<mixed>  $lineItems
     */
    public static function fingerprint(array $lineItems, mixed $saleDiscountInput, mixed $clientId, ?User $user): ?string
    {
        $discounts = self::manualDiscounts($lineItems, $saleDiscountInput, $clientId, $user);

        return $discounts === null ? null : hash('sha256', (string) json_encode($discounts));
    }
}
