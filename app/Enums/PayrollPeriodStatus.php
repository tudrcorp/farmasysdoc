<?php

namespace App\Enums;

enum PayrollPeriodStatus: string
{
    case Draft = 'draft';
    case Calculated = 'calculated';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Calculated => 'Calculada',
            self::Closed => 'Cerrada',
        };
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Calculated => 'warning',
            self::Closed => 'success',
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
