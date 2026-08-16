<?php

namespace App\Enums;

enum HrPayrollConceptBehavior: string
{
    case Fixed = 'fixed';
    case Percentage = 'percentage';

    public function label(): string
    {
        return match ($this) {
            self::Fixed => 'Monto fijo',
            self::Percentage => 'Porcentaje',
        };
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::Fixed => 'gray',
            self::Percentage => 'info',
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
