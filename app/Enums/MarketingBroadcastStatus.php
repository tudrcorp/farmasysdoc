<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

enum MarketingBroadcastStatus: string
{
    use HasSpanishLabels;

    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Processing = 'processing';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Borrador',
            self::Scheduled => 'Programado',
            self::Processing => 'Enviando',
            self::Completed => 'Completado',
            self::Failed => 'Fallido',
        };
    }
}
