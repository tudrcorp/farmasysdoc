<?php

namespace App\Enums;

enum PettyCashFundStatus: string
{
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Depleted = 'depleted';
    case Replenished = 'replenished';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingApproval => 'Por aprobar',
            self::Approved => 'Aprobada',
            self::Rejected => 'Rechazada',
            self::Depleted => 'Agotada',
            self::Replenished => 'Cerrada por reposición',
            self::Cancelled => 'Cancelada',
        };
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::PendingApproval => 'warning',
            self::Approved => 'success',
            self::Rejected => 'danger',
            self::Depleted => 'info',
            self::Replenished => 'gray',
            self::Cancelled => 'gray',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::PendingApproval || $this === self::Approved;
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
