<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

enum MarketingCampaignStatus: string
{
    use HasSpanishLabels;

    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
    case Ended = 'ended';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Active => 'Activa',
            self::Paused => 'Pausada',
            self::Ended => 'Finalizada',
        };
    }
}
