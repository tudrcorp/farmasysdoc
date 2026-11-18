<?php

namespace App\Filament\BusinessPartners\Resources\PanelActivities\Pages;

use App\Filament\BusinessPartners\Resources\PanelActivities\PanelActivityResource;
use Filament\Resources\Pages\ListRecords;

class ListPanelActivities extends ListRecords
{
    protected static string $resource = PanelActivityResource::class;

    public function getTitle(): string
    {
        return 'Bitácora';
    }

    public function getSubheading(): ?string
    {
        return 'Registro inalterable de los cambios de usuarios, permisos y afiliados de su empresa, hechos por su equipo o por Farmadoc.';
    }
}
