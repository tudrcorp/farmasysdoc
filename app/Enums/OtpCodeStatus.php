<?php

namespace App\Enums;

/**
 * Estado de una clave OTP. «Vencida» se deriva de `expires_at` aunque la fila siga en «pending».
 */
enum OtpCodeStatus: string
{
    case Pending = 'pending';
    case Used = 'used';
    case Expired = 'expired';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Vigente',
            self::Used => 'Usada',
            self::Expired => 'Vencida',
            self::Cancelled => 'Anulada',
        };
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::Pending => 'success',
            self::Used => 'gray',
            self::Expired => 'warning',
            self::Cancelled => 'danger',
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
