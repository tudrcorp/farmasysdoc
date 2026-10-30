<?php

namespace App\Enums;

enum EmployeeSettlementLineType: string
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
