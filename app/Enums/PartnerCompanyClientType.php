<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

/**
 * Tipo de cliente de la compañía aliada (segmento comercial del convenio).
 */
enum PartnerCompanyClientType: string
{
    use HasSpanishLabels;

    case Corporate = 'empresa';
    case HealthCenter = 'centro-salud';
    case Insurer = 'aseguradora';
    case Pharmacy = 'farmacia';
    case PublicInstitution = 'institucion-publica';
    case Foundation = 'fundacion';
    case Other = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Corporate => 'Empresa',
            self::HealthCenter => 'Clínica / centro de salud',
            self::Insurer => 'Aseguradora',
            self::Pharmacy => 'Farmacia',
            self::PublicInstitution => 'Institución pública',
            self::Foundation => 'Fundación / ONG',
            self::Other => 'Otro',
        };
    }
}
