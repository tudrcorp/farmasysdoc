<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

/**
 * Qué tan legible es la foto del récipe según el modelo de visión.
 */
enum PrescriptionLegibility: string
{
    use HasSpanishLabels;

    case Legible = 'legible';
    case Doubtful = 'dudosa';
    case Illegible = 'ilegible';

    public function label(): string
    {
        return match ($this) {
            self::Legible => 'Legible',
            self::Doubtful => 'Dudosa',
            self::Illegible => 'Ilegible',
        };
    }

    public function filamentColor(): string
    {
        return match ($this) {
            self::Legible => 'success',
            self::Doubtful => 'warning',
            self::Illegible => 'danger',
        };
    }
}
