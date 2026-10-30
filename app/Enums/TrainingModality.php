<?php

namespace App\Enums;

enum TrainingModality: string
{
    case InPerson = 'in_person';
    case Online = 'online';

    public function label(): string
    {
        return match ($this) {
            self::InPerson => 'Presencial',
            self::Online => 'En línea',
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
