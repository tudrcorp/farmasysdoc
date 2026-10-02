<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

enum FiscalPrinterModel: string
{
    use HasSpanishLabels;

    case AclasPp9Plus = 'aclas_pp9_plus';
    case HkaGeneric = 'hka_generico';

    public function label(): string
    {
        return match ($this) {
            self::AclasPp9Plus => 'Aclas PP9-PLUS (The Factory HKA)',
            self::HkaGeneric => 'Otro modelo con protocolo HKA',
        };
    }

    /**
     * Protocolo que el agente local debe usar para hablar con el equipo.
     */
    public function protocol(): string
    {
        return match ($this) {
            self::AclasPp9Plus, self::HkaGeneric => 'hka',
        };
    }
}
