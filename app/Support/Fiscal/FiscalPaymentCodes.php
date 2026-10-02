<?php

namespace App\Support\Fiscal;

/**
 * Códigos lógicos de medio de pago que Farmadoc envía al agente fiscal. Cada máquina fiscal asigna
 * a cada código el número de medio de pago (01-24) programado en su firmware.
 */
final class FiscalPaymentCodes
{
    /**
     * @return array<string, string>
     */
    public static function labels(): array
    {
        return [
            'cash_ves' => 'Efectivo Bs',
            'cash_usd' => 'Efectivo USD (divisas)',
            'card_ves' => 'Punto de venta (débito/crédito)',
            'mobile_payment_ves' => 'Pago móvil',
            'transfer_ves' => 'Transferencia Bs',
            'transfer_usd' => 'Transferencia USD',
            'zelle' => 'Zelle',
            'cashea' => 'Cashea',
            'other_ves' => 'Otro medio en Bs',
        ];
    }

    /**
     * @return list<string>
     */
    public static function codes(): array
    {
        return array_keys(self::labels());
    }

    public static function isValidSlot(mixed $slot): bool
    {
        return is_string($slot)
            && preg_match('/^\d{2}$/', $slot) === 1
            && (int) $slot >= 1
            && (int) $slot <= 24;
    }

    /**
     * @param  array<string, mixed>|null  $slots
     * @return list<string> Códigos sin número válido asignado.
     */
    public static function missing(?array $slots): array
    {
        return array_values(array_filter(
            self::codes(),
            fn (string $code): bool => ! self::isValidSlot($slots[$code] ?? null),
        ));
    }
}
