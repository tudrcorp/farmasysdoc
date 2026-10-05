<?php

namespace App\Support\Fiscal;

/**
 * Longitudes de los campos numéricos del protocolo HKA según el flag 21 de la máquina fiscal
 * (lo informa el diagnóstico del agente). Las claves coinciden con HkaCommandFormat del agente Windows.
 */
final class HkaFlag21Formats
{
    /**
     * @var array<string, array{label: string, format: array<string, int>}>
     */
    private const FORMATS = [
        '00' => [
            'label' => '00 · Estándar: precio 8+2, cantidad 5+3, pagos 10+2',
            'format' => [
                'price_integer_digits' => 8,
                'price_decimals' => 2,
                'quantity_integer_digits' => 5,
                'quantity_decimals' => 3,
                'discount_integer_digits' => 7,
                'discount_decimals' => 2,
                'payment_integer_digits' => 10,
                'payment_decimals' => 2,
            ],
        ],
        '01' => [
            'label' => '01 · Precio 7+3, cantidad 5+3, pagos 10+2',
            'format' => [
                'price_integer_digits' => 7,
                'price_decimals' => 3,
                'quantity_integer_digits' => 5,
                'quantity_decimals' => 3,
                'discount_integer_digits' => 7,
                'discount_decimals' => 2,
                'payment_integer_digits' => 10,
                'payment_decimals' => 2,
            ],
        ],
        '02' => [
            'label' => '02 · Precio 6+4, cantidad 5+3, pagos 10+2',
            'format' => [
                'price_integer_digits' => 6,
                'price_decimals' => 4,
                'quantity_integer_digits' => 5,
                'quantity_decimals' => 3,
                'discount_integer_digits' => 7,
                'discount_decimals' => 2,
                'payment_integer_digits' => 10,
                'payment_decimals' => 2,
            ],
        ],
        '30' => [
            'label' => '30 · Montos extendidos: precio 14+2, cantidad 14+3, pagos 15+2',
            'format' => [
                'price_integer_digits' => 14,
                'price_decimals' => 2,
                'quantity_integer_digits' => 14,
                'quantity_decimals' => 3,
                'discount_integer_digits' => 15,
                'discount_decimals' => 2,
                'payment_integer_digits' => 15,
                'payment_decimals' => 2,
            ],
        ],
    ];

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_map(fn (array $definition): string => $definition['label'], self::FORMATS);
    }

    /**
     * @return array<string, int>|null
     */
    public static function format(?string $flag21): ?array
    {
        return self::FORMATS[$flag21]['format'] ?? null;
    }
}
