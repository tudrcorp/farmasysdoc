<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

/**
 * Quién responde la conversación: el asistente (n8n) o un analista desde el panel.
 */
enum WhatsappConversationHandler: string
{
    use HasSpanishLabels;

    case Agent = 'agent';
    case Analyst = 'analyst';

    public function label(): string
    {
        return match ($this) {
            self::Agent => 'Asistente',
            self::Analyst => 'Analista',
        };
    }
}
