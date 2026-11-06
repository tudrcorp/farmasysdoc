<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

/**
 * Tipo de versión de convenio con una compañía aliada.
 */
enum PartnerAgreementType: string
{
    use HasSpanishLabels;

    case Initial = 'inicial';
    case Renewal = 'renovacion';
    case Amendment = 'modificacion';
    case Renegotiation = 'renegociacion';

    public function label(): string
    {
        return match ($this) {
            self::Initial => 'Convenio inicial',
            self::Renewal => 'Renovación',
            self::Amendment => 'Modificación / adenda',
            self::Renegotiation => 'Renegociación',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Initial => 'primary',
            self::Renewal => 'success',
            self::Amendment => 'info',
            self::Renegotiation => 'warning',
        };
    }

    /**
     * Renovación, modificación y renegociación parten de un convenio anterior.
     */
    public function requiresPreviousAgreement(): bool
    {
        return $this !== self::Initial;
    }

    /**
     * Modificación y renegociación cambian un convenio vigente dentro de su plazo.
     */
    public function changesWithinTerm(): bool
    {
        return $this === self::Amendment || $this === self::Renegotiation;
    }
}
