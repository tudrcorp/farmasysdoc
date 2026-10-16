<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

/**
 * Cómo llegaron al sistema los totales de un reporte Z (siempre leídos de la máquina fiscal, nunca tipeados).
 */
enum FiscalZReportSource: string
{
    use HasSpanishLabels;

    case AfterPrint = 'impresion_z';
    case MemoryRead = 'lectura_memoria';

    public function label(): string
    {
        return match ($this) {
            self::AfterPrint => 'Al imprimir el Z',
            self::MemoryRead => 'Lectura de memoria fiscal',
        };
    }
}
