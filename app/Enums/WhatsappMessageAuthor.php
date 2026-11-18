<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

enum WhatsappMessageAuthor: string
{
    use HasSpanishLabels;

    case Customer = 'customer';
    case Agent = 'agent';
    case Analyst = 'analyst';
    case System = 'system';

    public function label(): string
    {
        return match ($this) {
            self::Customer => 'Cliente',
            self::Agent => 'Asistente',
            self::Analyst => 'Analista',
            self::System => 'Sistema',
        };
    }

    public function isOutbound(): bool
    {
        return in_array($this, [self::Agent, self::Analyst], true);
    }
}
