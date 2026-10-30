<?php

namespace App\Enums;

enum RecruitmentCvExtractionStatus: string
{
    case Completed = 'completed';
    case Failed = 'failed';
    case Skipped = 'skipped';

    public function label(): string
    {
        return match ($this) {
            self::Completed => 'Leído por IA',
            self::Failed => 'No se pudo leer',
            self::Skipped => 'Carga manual',
        };
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::Completed => 'success',
            self::Failed => 'danger',
            self::Skipped => 'gray',
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
