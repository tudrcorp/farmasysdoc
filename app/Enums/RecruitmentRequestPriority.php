<?php

namespace App\Enums;

enum RecruitmentRequestPriority: string
{
    case Normal = 'normal';
    case High = 'high';
    case Urgent = 'urgent';

    public function label(): string
    {
        return match ($this) {
            self::Normal => 'Normal',
            self::High => 'Alta',
            self::Urgent => 'Urgente',
        };
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::Normal => 'gray',
            self::High => 'warning',
            self::Urgent => 'danger',
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
