<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

/**
 * Eventos registrados en la bitácora de un convenio.
 */
enum PartnerAgreementEventType: string
{
    use HasSpanishLabels;

    case Created = 'creado';
    case Updated = 'editado';
    case Submitted = 'enviado';
    case Approved = 'aprobado';
    case Rejected = 'rechazado';
    case Activated = 'activado';
    case Superseded = 'reemplazado';
    case Expired = 'vencido';
    case AutoRenewed = 'renovado-automaticamente';
    case Cancelled = 'anulado';
    case ExpiryNoticeSent = 'aviso-vencimiento';
    case TermsApplied = 'condiciones-aplicadas';

    public function label(): string
    {
        return match ($this) {
            self::Created => 'Creado',
            self::Updated => 'Editado',
            self::Submitted => 'Enviado a aprobación',
            self::Approved => 'Aprobado',
            self::Rejected => 'Rechazado',
            self::Activated => 'Entró en vigencia',
            self::Superseded => 'Reemplazado por otra versión',
            self::Expired => 'Vencido',
            self::AutoRenewed => 'Renovado automáticamente',
            self::Cancelled => 'Anulado',
            self::ExpiryNoticeSent => 'Aviso de vencimiento enviado',
            self::TermsApplied => 'Condiciones aplicadas al aliado',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Approved, self::Activated, self::AutoRenewed, self::TermsApplied => 'success',
            self::Rejected, self::Expired, self::Cancelled => 'danger',
            self::Submitted, self::ExpiryNoticeSent => 'warning',
            default => 'gray',
        };
    }
}
