<?php

namespace App\Filament\Resources\PhysicalCashBoxCloseReports\Pages;

use App\Filament\Resources\PhysicalCashBoxCloseReports\PhysicalCashBoxCloseReportResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListPhysicalCashBoxCloseReports extends ListRecords
{
    protected static string $resource = PhysicalCashBoxCloseReportResource::class;

    protected static ?string $title = 'Reportes de cierre de caja';

    public function getHeading(): string|Htmlable
    {
        return static::$title ?? 'Reportes de cierre de caja';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Reportes comparativos persistidos al cerrar la caja física. Quedan disponibles aunque falle WhatsApp o el correo.';
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
