<?php

namespace App\Enums;

enum HrLoanStatus: string
{
    case PendingApproval = 'pending_approval';
    case Active = 'active';
    case Rejected = 'rejected';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingApproval => 'Por aprobar',
            self::Active => 'Activo',
            self::Rejected => 'Rechazado',
            self::Paid => 'Saldado',
            self::Cancelled => 'Cancelado',
        };
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::PendingApproval => 'warning',
            self::Active => 'success',
            self::Rejected => 'danger',
            self::Paid => 'info',
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
