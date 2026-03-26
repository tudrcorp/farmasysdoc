<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

enum MarketingBroadcastSendMode: string
{
    use HasSpanishLabels;

    case All = 'all';
    case Segment = 'segment';
    case Selected = 'selected';

    public function label(): string
    {
        return match ($this) {
            self::All => 'Todos los clientes',
            self::Segment => 'Segmento',
            self::Selected => 'Clientes seleccionados',
        };
    }
}
