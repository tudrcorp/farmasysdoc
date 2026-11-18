<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

/**
 * Cómo llegó un afiliado a un plan de un aliado.
 */
enum PartnerAffiliateSource: string
{
    use HasSpanishLabels;

    case Counter = 'mostrador';
    case Import = 'carga';

    public function label(): string
    {
        return match ($this) {
            self::Counter => 'Mostrador',
            self::Import => 'Carga masiva',
        };
    }
}
