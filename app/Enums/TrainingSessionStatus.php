<?php

namespace App\Enums;

enum TrainingSessionStatus: string
{
    case Scheduled = 'scheduled';
    case InProgress = 'in_progress';
    case Finished = 'finished';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Scheduled => 'Programado',
            self::InProgress => 'En curso',
            self::Finished => 'Finalizado',
            self::Cancelled => 'Cancelado',
        };
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::Scheduled => 'info',
            self::InProgress => 'warning',
            self::Finished => 'success',
            self::Cancelled => 'gray',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Scheduled || $this === self::InProgress;
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
