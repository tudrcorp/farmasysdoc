<?php

namespace App\Filament\Resources\FiscalZReports\Pages;

use App\Filament\Resources\FiscalZReports\FiscalZReportResource;
use Filament\Resources\Pages\ListRecords;

class ListFiscalZReports extends ListRecords
{
    protected static string $resource = FiscalZReportResource::class;

    protected static ?string $title = 'Reportes Z';

    public function getSubheading(): ?string
    {
        return 'Cierres fiscales diarios leídos de las máquinas. Para imprimir un Z use la ficha de la máquina fiscal o el cierre del Libro de Ventas.';
    }
}
