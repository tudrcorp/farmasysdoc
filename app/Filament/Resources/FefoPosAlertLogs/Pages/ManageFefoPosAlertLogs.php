<?php

namespace App\Filament\Resources\FefoPosAlertLogs\Pages;

use App\Filament\Resources\FefoPosAlertLogs\FefoPosAlertLogResource;
use Filament\Resources\Pages\ManageRecords;
use Illuminate\Contracts\Support\Htmlable;

class ManageFefoPosAlertLogs extends ManageRecords
{
    protected static string $resource = FefoPosAlertLogResource::class;

    protected static ?string $title = 'Alertas FEFO en caja';

    public function getHeading(): string|Htmlable
    {
        return static::$title ?? 'Alertas FEFO en caja';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Monitoreo en tiempo casi real de alertas de lote por vencer en el POS. Revise si el cajero completó la venta después de la notificación.';
    }
}
