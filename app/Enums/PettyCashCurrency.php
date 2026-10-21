<?php

namespace App\Enums;

enum PettyCashCurrency: string
{
    case Usd = 'usd';
    case Ves = 'ves';

    public function label(): string
    {
        return match ($this) {
            self::Usd => 'Dólares (USD)',
            self::Ves => 'Bolívares (Bs.)',
        };
    }

    public function shortLabel(): string
    {
        return match ($this) {
            self::Usd => 'USD',
            self::Ves => 'Bs.',
        };
    }

    public function prefix(): string
    {
        return match ($this) {
            self::Usd => 'US$',
            self::Ves => 'Bs.',
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
