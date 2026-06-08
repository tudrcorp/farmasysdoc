<?php

namespace App\Support\Cash;

/**
 * Cálculo de montos en USD a partir de cantidades por denominación de billete.
 */
final class UsdBillDenominationCalculator
{
    /**
     * @return list<int>
     */
    public static function denominations(): array
    {
        return [100, 50, 20, 10, 5, 1];
    }

    /**
     * @param  array<int|string, int|string|null>  $counts
     */
    public static function totalFromCounts(array $counts): float
    {
        $total = 0.0;

        foreach (self::denominations() as $denomination) {
            $quantity = self::normalizeQuantity($counts[$denomination] ?? $counts[(string) $denomination] ?? 0);
            $total += $denomination * $quantity;
        }

        return round($total, 2);
    }

    /**
     * @param  array<int|string, int|string|null>  $counts
     * @return list<array{denomination: int, quantity: int, subtotal: float}>
     */
    public static function breakdownFromCounts(array $counts): array
    {
        $rows = [];

        foreach (self::denominations() as $denomination) {
            $quantity = self::normalizeQuantity($counts[$denomination] ?? $counts[(string) $denomination] ?? 0);
            $rows[] = [
                'denomination' => $denomination,
                'quantity' => $quantity,
                'subtotal' => round($denomination * $quantity, 2),
            ];
        }

        return $rows;
    }

    public static function normalizeQuantity(mixed $value): int
    {
        if (! is_numeric($value)) {
            $digits = preg_replace('/\D/', '', (string) $value);

            return max(0, (int) ($digits !== '' ? $digits : 0));
        }

        return max(0, (int) $value);
    }
}
