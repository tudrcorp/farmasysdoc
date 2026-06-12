<?php

namespace App\Enums;

enum FefoPosAlertSeverity: string
{
    case Critical = 'critical';
    case Warning = 'warning';

    public function label(): string
    {
        return match ($this) {
            self::Critical => 'Urgente',
            self::Warning => 'Advertencia',
        };
    }

    public function badgeColor(): string
    {
        return match ($this) {
            self::Critical => 'danger',
            self::Warning => 'warning',
        };
    }
}
