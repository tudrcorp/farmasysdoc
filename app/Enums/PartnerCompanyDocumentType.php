<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

/**
 * Tipos de documento adjunto al expediente de la compañía aliada.
 */
enum PartnerCompanyDocumentType: string
{
    use HasSpanishLabels;

    case AgreementLetter = 'carta-convenio';
    case Rif = 'rif';
    case LegalRepresentativeId = 'cedula-representante';
    case CommercialRegistry = 'registro-mercantil';
    case Other = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::AgreementLetter => 'Carta de convenio',
            self::Rif => 'RIF',
            self::LegalRepresentativeId => 'Cédula del representante legal',
            self::CommercialRegistry => 'Registro Mercantil',
            self::Other => 'Otro documento',
        };
    }
}
