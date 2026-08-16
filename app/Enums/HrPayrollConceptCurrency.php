<?php

namespace App\Enums;

enum HrPayrollConceptCurrency: string
{
    case Usd = 'usd';
    case Ves = 'ves';

    public function label(): string
    {
        return match ($this) {
            self::Usd => 'Dólares',
            self::Ves => 'Bolívares',
        };
    }

    public function prefix(): string
    {
        return match ($this) {
            self::Usd => 'US$',
            self::Ves => 'Bs',
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
}
