<?php

namespace App\Enums;

enum HrPayrollConceptApplication: string
{
    case Legal = 'legal';
    case Business = 'business';

    public function label(): string
    {
        return match ($this) {
            self::Legal => 'De ley',
            self::Business => 'Del negocio',
        };
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::Legal => 'warning',
            self::Business => 'info',
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
