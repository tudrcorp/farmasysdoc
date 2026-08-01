<?php

namespace App\Enums;

enum EmployeeBankAccountType: string
{
    case Checking = 'corriente';
    case Savings = 'ahorro';

    public function label(): string
    {
        return match ($this) {
            self::Checking => 'Corriente',
            self::Savings => 'Ahorro',
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
