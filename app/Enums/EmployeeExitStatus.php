<?php

namespace App\Enums;

enum EmployeeExitStatus: string
{
    case Open = 'open';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'En proceso',
            self::Closed => 'Cerrado',
            self::Cancelled => 'Cancelado',
        };
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::Closed => 'success',
            self::Cancelled => 'gray',
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
