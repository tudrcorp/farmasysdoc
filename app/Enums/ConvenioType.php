<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

/**
 * Tipo de convenio o cobertura asociada al pedido (alianzas con aseguradoras, EPS, etc.).
 */
enum ConvenioType: string
{
    use HasSpanishLabels;

    case Particular = 'particular';
    case PrivateInsurance = 'seguro-privado';
    case Eps = 'eps';
    case PrepaidMedicine = 'medicina-prepagada';
    case Corporate = 'convenio-corporativo';
    case Other = 'otro';

    public function label(): string
    {
        return match ($this) {
            self::Particular => 'Particular',
            self::PrivateInsurance => 'Seguro privado',
            self::Eps => 'EPS',
            self::PrepaidMedicine => 'Medicina prepagada',
            self::Corporate => 'Convenio corporativo',
            self::Other => 'Otro',
        };
    }
}
