<?php

namespace App\Support\Inventory;

/**
 * Formato de existencias para UI: enteros sin decimales innecesarios (4, no 4,000).
 */
final class InventoryQuantityFormat
{
    /**
     * Formato es-VE: coma decimal, punto de miles. 4.0 → «4»; 2.5 → «2,5».
     */
    public static function display(float|int|string|null $quantity): string
    {
        $quantity = round((float) $quantity, 3);

        if (abs($quantity - round($quantity)) < 0.0001) {
            return number_format((int) round($quantity), 0, ',', '.');
        }

        $formatted = rtrim(rtrim(number_format($quantity, 3, ',', '.'), '0'), ',');

        return $formatted !== '' ? $formatted : '0';
    }

    /**
     * Formato con punto decimal (mensajes técnicos / logs).
     */
    public static function displayDot(float|int|string|null $quantity): string
    {
        $quantity = round((float) $quantity, 3);

        if (abs($quantity - round($quantity)) < 0.0001) {
            return (string) (int) round($quantity);
        }

        $formatted = rtrim(rtrim(number_format($quantity, 3, '.', ''), '0'), '.');

        return $formatted !== '' ? $formatted : '0';
    }
}
