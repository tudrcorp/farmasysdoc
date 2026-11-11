<?php

namespace App\Enums;

/**
 * Módulo del sistema que solicita la clave OTP.
 */
enum OtpCodePurpose: string
{
    case InventoryAudit = 'inventory_audit';
    case ManualBdvConciliation = 'manual_bdv_conciliation';
    case PosBdvConciliation = 'pos_bdv_conciliation';
    case PosDiscount = 'pos_discount';

    public function label(): string
    {
        return match ($this) {
            self::InventoryAudit => 'Auditoría de inventario',
            self::ManualBdvConciliation => 'Conciliación BDV manual',
            self::PosBdvConciliation => 'Caja · Pago Móvil BDV manual',
            self::PosDiscount => 'Caja · Descuento manual',
        };
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::InventoryAudit => 'info',
            self::ManualBdvConciliation => 'primary',
            self::PosBdvConciliation => 'warning',
            self::PosDiscount => 'success',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->label();
        }

        return $out;
    }
}
