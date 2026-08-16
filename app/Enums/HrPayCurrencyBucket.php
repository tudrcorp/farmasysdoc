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

    public function paymentLabel(): string
    {
        return match ($this) {
            self::Usd => 'Dólares',
            self::Ves => 'Bolívares',
        };
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::Usd => 'success',
            self::Ves => 'info',
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

    /**
     * @return array<string, string>
     */
    public static function paymentOptions(): array
    {
        $out = [];
        foreach (self::cases() as $case) {
            $out[$case->value] = $case->paymentLabel();
        }

        return $out;
    }
}
