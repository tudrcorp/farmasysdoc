<?php

namespace App\Enums;

enum HrPayCurrencyBucket: string
{
    case Usd = 'usd';
    case Ves = 'ves';

    public function label(): string
    {
        return match ($this) {
            self::Usd => 'Dólares (efectivo)',
            self::Ves => 'Bolívares (tasa BCV)',
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
