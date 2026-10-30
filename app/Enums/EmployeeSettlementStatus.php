<?php

namespace App\Enums;

enum EmployeeSettlementStatus: string
{
    case Draft = 'draft';
    case PendingApproval = 'pending_approval';
    case Approved = 'approved';
    case Paid = 'paid';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::PendingApproval => 'Por aprobar',
            self::Approved => 'Aprobada',
            self::Paid => 'Pagada',
            self::Cancelled => 'Anulada',
        };
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::PendingApproval => 'warning',
            self::Approved => 'info',
            self::Paid => 'success',
            self::Cancelled => 'danger',
        };
    }

    public function isActive(): bool
    {
        return $this !== self::Cancelled;
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
