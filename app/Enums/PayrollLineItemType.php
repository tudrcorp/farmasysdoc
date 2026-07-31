<?php

namespace App\Enums;

enum PayrollLineItemType: string
{
    case Base = 'base';
    case Assignment = 'assignment';
    case Deduction = 'deduction';
    case Loan = 'loan';

    public function label(): string
    {
        return match ($this) {
            self::Base => 'Sueldo base',
            self::Assignment => 'Asignación',
            self::Deduction => 'Deducción',
            self::Loan => 'Préstamo',
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
