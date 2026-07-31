<?php

namespace App\Enums;

enum HrRecurrence: string
{
    case Once = 'once';
    case Recurring = 'recurring';

    public function label(): string
    {
        return match ($this) {
            self::Once => 'Puntual',
            self::Recurring => 'Recurrente',
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
