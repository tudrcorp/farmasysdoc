<?php

namespace App\Enums;

enum HrLoanFrequency: string
{
    case Biweekly = 'biweekly';
    case Monthly = 'monthly';

    public function label(): string
    {
        return match ($this) {
            self::Biweekly => 'Quincenal (15 y 30)',
            self::Monthly => 'Mensual (solo cierre de mes)',
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
