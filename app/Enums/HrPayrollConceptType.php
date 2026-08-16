<?php

namespace App\Enums;

enum HrPayrollConceptType: string
{
    case Assignment = 'assignment';
    case Deduction = 'deduction';

    public function label(): string
    {
        return match ($this) {
            self::Assignment => 'Asignación',
            self::Deduction => 'Deducción',
        };
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::Assignment => 'success',
            self::Deduction => 'danger',
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
