<?php

namespace App\Support\Inventory;

final class InventoryAdjustmentReason
{
    public const PURCHASE_ANNULMENT = 'purchase_annulment';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::PURCHASE_ANNULMENT => 'Anulación de compra',
        ];
    }

    public static function label(?string $value): string
    {
        if (blank($value)) {
            return '—';
        }

        return self::options()[$value] ?? $value;
    }

    /**
     * Color de badge Filament para el motivo.
     */
    public static function filamentColor(?string $value): string
    {
        return match ($value) {
            self::PURCHASE_ANNULMENT => 'danger',
            default => 'gray',
        };
    }
}
