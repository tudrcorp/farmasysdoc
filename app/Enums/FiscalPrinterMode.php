<?php

namespace App\Enums;

use App\Enums\Concerns\HasSpanishLabels;

/**
 * Qué hace Farmadoc con las ventas de la caja que tiene esta máquina fiscal.
 * Permite activar caja por caja sin afectar a las demás.
 */
enum FiscalPrinterMode: string
{
    use HasSpanishLabels;

    case Disabled = 'desactivada';
    case Simulation = 'simulacion';
    case Active = 'activa';

    public function label(): string
    {
        return match ($this) {
            self::Disabled => 'Desactivada',
            self::Simulation => 'Simulación',
            self::Active => 'Activa',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Disabled => 'La caja factura como hoy (otro sistema). El agente solo reporta su estado.',
            self::Simulation => 'La caja factura como hoy. Por cada venta el agente arma los comandos sin imprimir, para validar la configuración.',
            self::Active => 'Farmadoc factura en esta máquina fiscal.',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Disabled => 'gray',
            self::Simulation => 'warning',
            self::Active => 'success',
        };
    }
}
