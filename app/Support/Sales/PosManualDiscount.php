<?php

namespace App\Support\Sales;

use App\Models\User;

/**
 * Descuentos manuales de caja, en porcentaje.
 *
 * Un porcentaje en la línea reemplaza al del total y al comercial del cliente en ese producto.
 * Un porcentaje sobre el total reemplaza al comercial en las líneas que no tienen porcentaje propio.
 * Un valor sin el permiso correspondiente se ignora.
 */
final class PosManualDiscount
{
    public const SALE_PERMISSION = 'pos_sale_discount';

    public const LINE_PERMISSION = 'pos_line_discount';

    public static function userCanDiscountSaleTotal(?User $user): bool
    {
        return $user instanceof User && $user->canApplyPosSaleDiscount();
    }

    public static function userCanDiscountLines(?User $user): bool
    {
        return $user instanceof User && $user->canApplyPosLineDiscount();
    }

    /**
     * @return array{applied: bool, percent: float, invalid: bool, submitted: bool}
     */
    public static function parsePercent(mixed $value, bool $authorized): array
    {
        $submitted = self::wasSubmitted($value);

        if (! $authorized) {
            return [
                'applied' => false,
                'percent' => 0.0,
                'invalid' => false,
                'submitted' => $submitted,
            ];
        }

        if (! $submitted) {
            return [
                'applied' => false,
                'percent' => 0.0,
                'invalid' => false,
                'submitted' => false,
            ];
        }

        $normalized = self::normalizeDecimal($value);
        if ($normalized === null || ! preg_match('/^\d+(\.\d+)?$/', $normalized)) {
            return [
                'applied' => false,
                'percent' => 0.0,
                'invalid' => true,
                'submitted' => true,
            ];
        }

        $percent = round((float) $normalized, 2);
        if ($percent < 0 || $percent > 100) {
            return [
                'applied' => false,
                'percent' => 0.0,
                'invalid' => true,
                'submitted' => true,
            ];
        }

        if ($percent <= 0.00001) {
            return [
                'applied' => false,
                'percent' => 0.0,
                'invalid' => false,
                'submitted' => false,
            ];
        }

        return [
            'applied' => true,
            'percent' => $percent,
            'invalid' => false,
            'submitted' => true,
        ];
    }

    public static function effectivePercent(
        float $linePercent,
        bool $lineApplied,
        float $salePercent,
        bool $saleApplied,
        float $commercialPercent,
    ): float {
        if ($lineApplied) {
            return max(0.0, min(100.0, $linePercent));
        }

        if ($saleApplied) {
            return max(0.0, min(100.0, $salePercent));
        }

        return max(0.0, min(100.0, $commercialPercent));
    }

    private static function wasSubmitted(mixed $value): bool
    {
        if ($value === null) {
            return false;
        }

        if (is_string($value) && trim($value) === '') {
            return false;
        }

        if (is_array($value)) {
            return $value !== [];
        }

        $normalized = self::normalizeDecimal($value);
        if ($normalized === null || $normalized === '' || ! preg_match('/^\d+(\.\d+)?$/', $normalized)) {
            return true;
        }

        return round((float) $normalized, 2) > 0.0;
    }

    private static function normalizeDecimal(mixed $value): ?string
    {
        if (is_int($value)) {
            return (string) $value;
        }

        if (is_float($value)) {
            if (! is_finite($value)) {
                return null;
            }

            $formatted = rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');

            return $formatted === '' ? '0' : $formatted;
        }

        if (! is_string($value)) {
            return null;
        }

        return trim(str_replace(',', '.', $value));
    }
}
