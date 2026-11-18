<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

/**
 * Estado de una conversación de WhatsApp en la bandeja de analistas.
 */
enum WhatsappConversationStatus: string
{
    use HasSpanishLabels;

    case Bot = 'bot';
    case NeedsAnalyst = 'requiere-analista';
    case PendingNextShift = 'pendiente-jornada';
    case InProgress = 'en-gestion';
    case Converted = 'pedido-creado';
    case Closed = 'cerrada';

    public function label(): string
    {
        return match ($this) {
            self::Bot => 'Atendida por el asistente',
            self::NeedsAnalyst => 'Requiere analista',
            self::PendingNextShift => 'Pendiente para la jornada',
            self::InProgress => 'En gestión',
            self::Converted => 'Pedido creado',
            self::Closed => 'Cerrada',
        };
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::Bot => 'info',
            self::NeedsAnalyst => 'danger',
            self::PendingNextShift => 'warning',
            self::InProgress => 'primary',
            self::Converted => 'success',
            self::Closed => 'gray',
        };
    }

    public function isOpen(): bool
    {
        return ! in_array($this, [self::Converted, self::Closed], true);
    }

    /**
     * Estados que esperan a que un analista tome la conversación.
     *
     * @return list<self>
     */
    public static function awaitingAnalyst(): array
    {
        return [self::NeedsAnalyst, self::PendingNextShift];
    }
}
