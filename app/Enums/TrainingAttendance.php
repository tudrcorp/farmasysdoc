<?php

namespace App\Enums;

enum TrainingAttendance: string
{
    case Pending = 'pending';
    case Attended = 'attended';
    case Absent = 'absent';
    case Excused = 'excused';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Por marcar',
            self::Attended => 'Asistió',
            self::Absent => 'Faltó',
            self::Excused => 'Justificó',
        };
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Attended => 'success',
            self::Absent => 'danger',
            self::Excused => 'warning',
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
