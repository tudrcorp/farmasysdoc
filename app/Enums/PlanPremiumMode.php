<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

/**
 * Modalidad de la tarifa de un plan corporativo.
 */
enum PlanPremiumMode: string
{
    use HasSpanishLabels;

    case Flat = 'tarifa_unica';
    case AgeRanges = 'por_rango_edad';
    case Retail = 'retail';
    case ConsumptionFund = 'fondo_consumo';
    case AffiliateBands = 'por_afiliados';

    public function label(): string
    {
        return match ($this) {
            self::Flat => 'Tarifa única',
            self::AgeRanges => 'Por rango de edad',
            self::Retail => 'Retail',
            self::ConsumptionFund => 'Fondo de consumo',
            self::AffiliateBands => 'Por cantidad de afiliados',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Flat => 'Una sola prima mensual por persona para todas las edades.',
            self::AgeRanges => 'La prima mensual por persona depende del rango de edad.',
            self::Retail => 'Sin prima mensual: el beneficiario compra a precio de venta al público.',
            self::ConsumptionFund => 'Sin prima mensual: cada aliado tiene un monto de consumo autorizado y, según la negociación, porcentajes de ganancia.',
            self::AffiliateBands => 'Baremo: USD por afiliado al mes según el tramo de afiliados contratados; puede dividirse en subplanes.',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Flat => 'primary',
            self::AgeRanges => 'gray',
            self::Retail => 'warning',
            self::ConsumptionFund => 'success',
            self::AffiliateBands => 'info',
        };
    }
}
