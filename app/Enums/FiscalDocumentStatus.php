<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

enum FiscalDocumentStatus: string
{
    use HasSpanishLabels;

    case Pending = 'pendiente';
    case Claimed = 'tomado';
    case Printing = 'imprimiendo';
    case Printed = 'impreso';
    case Failed = 'fallido';
    case NeedsReview = 'requiere_revision';
    case Cancelled = 'anulado';
    case Simulated = 'simulado';

    public function label(): string
    {
        return match ($this) {
            self::Simulated => 'Simulado (no impreso)',
            self::Pending => 'Pendiente',
            self::Claimed => 'Tomado por el agente',
            self::Printing => 'Imprimiendo',
            self::Printed => 'Impreso',
            self::Failed => 'Fallido',
            self::NeedsReview => 'Requiere revisión',
            self::Cancelled => 'Anulado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Pending, self::Claimed => 'gray',
            self::Printing => 'info',
            self::Printed => 'success',
            self::Failed => 'danger',
            self::NeedsReview => 'warning',
            self::Cancelled => 'gray',
            self::Simulated => 'info',
        };
    }

    /**
     * Estados en los que el agente aún tiene el documento en curso.
     *
     * @return list<self>
     */
    public static function inFlight(): array
    {
        return [self::Claimed, self::Printing];
    }

    public function isFinal(): bool
    {
        return in_array($this, [self::Printed, self::Cancelled, self::Simulated], true);
    }
}
