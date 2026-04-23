<?php

namespace App\Support\Purchases;

/**
 * Forma de pago (histórico / abonos a CxP).
 */
final class PurchaseHistoryPaymentForm
{
    public const PAGO_UNICO = 'pago_unico';

    public const ABONO_PARCIAL = 'abono_parcial';

    public const LIQUIDACION_TOTAL = 'liquidacion_total';

    public const COMPENSACION = 'compensacion';

    public const OTRA = 'otra';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::PAGO_UNICO => 'Pago único / contado',
            self::ABONO_PARCIAL => 'Abono parcial',
            self::LIQUIDACION_TOTAL => 'Liquidación total',
            self::COMPENSACION => 'Compensación',
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
