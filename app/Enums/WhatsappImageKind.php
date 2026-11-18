<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

/**
 * Qué es la foto que envió el cliente por WhatsApp.
 */
enum WhatsappImageKind: string
{
    use HasSpanishLabels;

    case Recipe = 'recipe';
    case PaymentProof = 'payment_proof';
    case Product = 'product';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Recipe => 'Récipe médico',
            self::PaymentProof => 'Comprobante de pago',
            self::Product => 'Foto de producto',
            self::Other => 'Otra imagen',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Recipe => 'heroicon-m-document-text',
            self::PaymentProof => 'heroicon-m-banknotes',
            self::Product => 'heroicon-m-cube',
            self::Other => 'heroicon-m-photo',
        };
    }
}
