<?php

namespace App\Support\Inventory;

final class InventoryAdjustmentReason
{
    public const PURCHASE_ANNULMENT = 'purchase_annulment';

    public const BONIFICATIONS_PLUS = 'bonificaciones_plus';

    public const SOBRANTE_PLUS = 'sobrante_plus';

    public const CARGO_INVENTARIO_PLUS = 'cargo_inventario_plus';

    public const ERROR_EN_CARGA_PLUS = 'error_en_carga_plus';

    public const ERROR_EN_CARGA_MINUS = 'error_en_carga_minus';

    public const FALTANTE_MINUS = 'faltante_minus';

    public const VENCIDO_MINUS = 'vencido_minus';

    public const USO_INTERNO_MINUS = 'uso_interno_minus';

    public const USO_CORPORATIVO_MINUS = 'uso_corporativo_minus';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::PURCHASE_ANNULMENT => 'Anulación de compra',
            self::BONIFICATIONS_PLUS => 'Bonificaciones (+)',
            self::SOBRANTE_PLUS => 'Sobrante (+)',
            self::CARGO_INVENTARIO_PLUS => 'Cargo de inventario (+)',
            self::ERROR_EN_CARGA_PLUS => 'Error en Carga (+)',
            self::ERROR_EN_CARGA_MINUS => 'Error en Carga (-)',
            self::FALTANTE_MINUS => 'Faltante (-)',
            self::VENCIDO_MINUS => 'Vencido (-)',
            self::USO_INTERNO_MINUS => 'Uso Interno (-)',
            self::USO_CORPORATIVO_MINUS => 'Uso Corporativo (-)',
        ];
    }

    public static function label(?string $value): string
    {
        if (blank($value)) {
            return '—';
        }

        return self::options()[$value] ?? $value;
    }

    public static function quantitySign(string $reason): int
    {
        return match ($reason) {
            self::BONIFICATIONS_PLUS,
            self::SOBRANTE_PLUS,
            self::CARGO_INVENTARIO_PLUS,
            self::ERROR_EN_CARGA_PLUS => 1,

            self::ERROR_EN_CARGA_MINUS,
            self::FALTANTE_MINUS,
            self::VENCIDO_MINUS,
            self::USO_INTERNO_MINUS,
            self::USO_CORPORATIVO_MINUS,
            self::PURCHASE_ANNULMENT => -1,

            default => 0,
        };
    }

    public static function isPositive(string $reason): bool
    {
        return self::quantitySign($reason) > 0;
    }

    /**
     * Color de badge Filament para el motivo.
     */
    public static function filamentColor(?string $value): string
    {
        if (blank($value)) {
            return 'gray';
        }

        $sign = self::quantitySign((string) $value);
        if ($sign > 0) {
            return 'success';
        }

        if ($sign < 0) {
            return 'danger';
        }

        return 'gray';
    }
}
