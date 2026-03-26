<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

enum MarketingBroadcastType: string
{
    use HasSpanishLabels;

    case Campaign = 'campaign';
    case Promotion = 'promotion';
    case Reminder = 'reminder';

    public function label(): string
    {
        return match ($this) {
            self::Campaign => 'Campaña',
            self::Promotion => 'Promoción',
            self::Reminder => 'Recordatorio',
        };
    }
}
