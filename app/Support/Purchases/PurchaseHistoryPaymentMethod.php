<?php

namespace App\Support\Purchases;

/**
 * Método de pago al proveedor (histórico / abonos a CxP).
 */
final class PurchaseHistoryPaymentMethod
{
    public const TRANSFERENCIA = 'transferencia';

    public const EFECTIVO = 'efectivo';

    public const CHEQUE = 'cheque';

    public const PUNTO_VENTA = 'punto_venta';

    public const PAGO_MOVIL = 'pago_movil';

    public const ZELLE_USD = 'zelle_usd';

    public const OTRA = 'otra';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::TRANSFERENCIA => 'Transferencia bancaria',
            self::EFECTIVO => 'Efectivo',
            self::CHEQUE => 'Cheque',
            self::PUNTO_VENTA => 'Punto de venta / datáfono',
            self::PAGO_MOVIL => 'Pago móvil',
            self::ZELLE_USD => 'Zelle (USD)',
            self::OTRA => 'Otra',
        ];
    }

    public static function label(?string $value): string
    {
        if (blank($value)) {
            return '—';
        }

        return self::options()[$value] ?? $value;
    }
}
