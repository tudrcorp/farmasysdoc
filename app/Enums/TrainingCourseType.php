<?php

namespace App\Enums;

enum TrainingCourseType: string
{
    case Workshop = 'workshop';
    case Training = 'training';
    case Induction = 'induction';

    public function label(): string
    {
        return match ($this) {
            self::Workshop => 'Taller',
            self::Training => 'Capacitación',
            self::Induction => 'Inducción',
        };
    }

    /**
     * Con artículo, para la frase del certificado.
     */
    public function certificateLabel(): string
    {
        return match ($this) {
            self::Workshop => 'el taller',
            self::Training => 'la capacitación',
            self::Induction => 'la inducción',
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
