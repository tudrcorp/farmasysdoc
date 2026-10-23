<?php

namespace App\Enums;

enum EmploymentCertificateSalaryBasis: string
{
    case Integral = 'integral';
    case Legal = 'legal';

    public function label(): string
    {
        return match ($this) {
            self::Integral => 'Sueldo integral',
            self::Legal => 'Sueldo de ley',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Integral => 'Todo lo que cobras al mes, en dólares.',
            self::Legal => 'El sueldo de ley en bolívares.',
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
