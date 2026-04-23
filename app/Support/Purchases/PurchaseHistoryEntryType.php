<?php

namespace App\Support\Purchases;

/**
 * Tipo de fila en el histórico de compras.
 */
final class PurchaseHistoryEntryType
{
    public const COMPRA_CONTADO = 'compra_contado';

    public const PAGO_CUENTA_POR_PAGAR = 'pago_cuenta_por_pagar';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::COMPRA_CONTADO => 'Compra pagada de contado',
            self::PAGO_CUENTA_POR_PAGAR => 'Pago a cuenta por pagar',
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
