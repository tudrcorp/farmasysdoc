<?php

namespace App\Enums;

enum InventoryAuditLineStatus: string
{
    case Pending = 'pending';
    case Verified = 'verified';
    case Updated = 'updated';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pendiente',
            self::Verified => 'Procesado',
            self::Updated => 'Actualizado',
        };
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::Pending => 'gray',
            self::Verified => 'success',
            self::Updated => 'warning',
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
