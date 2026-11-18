<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

enum WhatsappMessageType: string
{
    use HasSpanishLabels;

    case Text = 'text';
    case Image = 'image';
    case Audio = 'audio';
    case Document = 'document';
    case Location = 'location';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Text => 'Texto',
            self::Image => 'Imagen',
            self::Audio => 'Nota de voz',
            self::Document => 'Documento',
            self::Location => 'Ubicación',
            self::Other => 'Otro',
        };
    }

    public function hasMedia(): bool
    {
        return in_array($this, [self::Image, self::Audio, self::Document], true);
    }

    /**
     * Tipos de mensaje que envía Ultramsg en el campo `data.type`.
     */
    public static function fromUltramsg(string $type): self
    {
        return match (mb_strtolower(trim($type))) {
            'chat', 'text' => self::Text,
            'image' => self::Image,
            'ptt', 'audio' => self::Audio,
            'document' => self::Document,
            'location' => self::Location,
            default => self::Other,
        };
    }
}
