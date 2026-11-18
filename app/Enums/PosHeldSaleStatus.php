<?php

namespace App\Enums;

/**
 * Venta en pausa en la caja: queda «En pausa» hasta que se cobra o se elimina.
 */
enum PosHeldSaleStatus: string
{
    case Paused = 'paused';
    case Completed = 'completed';
    case Discarded = 'discarded';

    public function label(): string
    {
        return match ($this) {
            self::Paused => 'En pausa',
            self::Completed => 'Cobrada',
            self::Discarded => 'Eliminada',
        };
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::Paused => 'warning',
            self::Completed => 'success',
            self::Discarded => 'danger',
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
