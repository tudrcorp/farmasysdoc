<?php

namespace App\Enums;

enum FinancialFixedExpenseCategory: string
{
    case Rent = 'rent';
    case Utilities = 'utilities';
    case Insurance = 'insurance';
    case Services = 'services';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Rent => 'Alquiler',
            self::Utilities => 'Servicios públicos',
            self::Insurance => 'Seguros',
            self::Services => 'Servicios profesionales',
            self::Other => 'Otro',
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
