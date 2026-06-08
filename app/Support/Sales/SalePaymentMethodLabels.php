<?php

namespace App\Support\Sales;

final class SalePaymentMethodLabels
{
    /**
     * Orden preferido en gráficos y totales del dashboard.
     *
     * @return list<string>
     */
    public static function dashboardOrder(): array
    {
        return [
            'efectivo_usd',
            'efectivo_ves',
            'punto_venta_ves',
            'transfer_ves',
            'zelle',
            'cachea',
            'pago_movil',
            'mixed',
            'credito_cliente',
            'transfer_usd',
        ];
    }

    public static function label(?string $value): string
    {
        if (blank($value)) {
            return 'Sin método';
        }

        $key = strtolower(trim($value));

        return match ($key) {
            'efectivo_usd' => 'Efectivo USD',
            'efectivo_ves' => 'Efectivo VES',
            'transfer_ves' => 'Transferencia VES',
            'zelle' => 'Zelle',
            'cachea' => 'Cachea',
            'pago_movil' => 'Pago móvil',
            'mixed' => 'Pago múltiple',
            'credito_cliente' => 'Crédito · CxC',
            'punto_venta_ves' => 'Punto de venta',
            'transfer_usd' => 'Transferencia USD',
            'cash', 'efectivo' => 'Efectivo',
            'card', 'tarjeta', 'debit', 'credit' => 'Tarjeta',
            'transfer', 'transferencia', 'nequi', 'daviplata' => 'Transferencia / digital',
            default => (string) $value,
        };
    }
}
