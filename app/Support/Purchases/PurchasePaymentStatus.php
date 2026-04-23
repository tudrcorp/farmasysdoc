<?php

namespace App\Support\Purchases;

use App\Models\Purchase;

/**
 * Valores almacenados en {@see Purchase::$payment_status} para el pago al proveedor.
 */
final class PurchasePaymentStatus
{
    public const PAGADO_CONTADO = 'pagado_contado';

    public const A_CREDITO = 'a_credito';

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return [
            self::PAGADO_CONTADO => 'Pagado de Contado',
            self::A_CREDITO => 'A Crédito',
        ];
    }

    public static function label(?string $value): string
    {
        if (blank($value)) {
            return '—';
        }

        return self::options()[$value] ?? self::labelLegacy($value);
    }

    private static function labelLegacy(string $value): string
    {
        $key = strtolower(trim($value));

        return match ($key) {
            self::PAGADO_CONTADO, 'paid', 'pagado', 'pagada' => self::options()[self::PAGADO_CONTADO],
            self::A_CREDITO, 'pending', 'pendiente', 'a credito', 'a crédito' => self::options()[self::A_CREDITO],
            'partial', 'parcial' => 'Parcial (histórico)',
            default => $value,
        };
    }

    /**
     * @return list<string>
     */
    public static function storedValues(): array
    {
        return [self::PAGADO_CONTADO, self::A_CREDITO];
    }
}
