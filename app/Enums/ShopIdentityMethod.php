<?php

namespace App\Enums;

enum ShopIdentityMethod: string
{
    case Document = 'document';
    case Phone = 'phone';

    public function label(): string
    {
        return match ($this) {
            self::Document => 'Cédula',
            self::Phone => 'Teléfono',
        };
    }
}
