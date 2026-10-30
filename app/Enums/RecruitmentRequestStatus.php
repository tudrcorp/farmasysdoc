<?php

namespace App\Enums;

enum RecruitmentRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Filled = 'filled';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Por aprobar',
            self::Approved => 'Aprobada',
            self::Rejected => 'Rechazada',
            self::Filled => 'Cubierta',
            self::Cancelled => 'Cancelada',
        };
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::Pending => 'warning',
            self::Approved => 'info',
            self::Rejected => 'danger',
            self::Filled => 'success',
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
