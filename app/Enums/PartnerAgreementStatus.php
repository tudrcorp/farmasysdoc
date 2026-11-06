<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

/**
 * Estados del ciclo de vida de un convenio con compañía aliada.
 *
 * Borrador → Por aprobar → (Aprobado →) Vigente → Vencido | Reemplazado | Anulado; Por aprobar → Rechazado → Borrador.
 */
enum PartnerAgreementStatus: string
{
    use HasSpanishLabels;

    case Draft = 'borrador';
    case PendingApproval = 'por-aprobar';
    case Approved = 'aprobado';
    case Active = 'vigente';
    case Expired = 'vencido';
    case Superseded = 'reemplazado';
    case Rejected = 'rechazado';
    case Cancelled = 'anulado';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::PendingApproval => 'Por aprobar',
            self::Approved => 'Aprobado (por iniciar)',
            self::Active => 'Vigente',
            self::Expired => 'Vencido',
            self::Superseded => 'Reemplazado',
            self::Rejected => 'Rechazado',
            self::Cancelled => 'Anulado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::PendingApproval => 'warning',
            self::Approved => 'info',
            self::Active => 'success',
            self::Expired => 'danger',
            self::Superseded => 'gray',
            self::Rejected => 'danger',
            self::Cancelled => 'gray',
        };
    }

    /**
     * Estados que aún no son definitivos: como máximo uno por aliado.
     *
     * @return list<self>
     */
    public static function inFlight(): array
    {
        return [self::Draft, self::PendingApproval, self::Approved, self::Rejected];
    }

    /**
     * Estados que ocupan un rango de vigencia (no pueden solaparse entre sí).
     *
     * @return list<self>
     */
    public static function occupyingTerm(): array
    {
        return [self::Approved, self::Active];
    }

    public function isEditable(): bool
    {
        return $this === self::Draft || $this === self::Rejected;
    }
}
