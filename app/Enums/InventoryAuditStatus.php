<?php

namespace App\Enums;

enum InventoryAuditStatus: string
{
    case Open = 'open';
    case Closed = 'closed';

    public function label(): string
    {
        return match ($this) {
            self::Open => 'Abierta',
            self::Closed => 'Cerrada',
        };
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::Open => 'warning',
            self::Closed => 'success',
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
