<?php

namespace App\Filament\Resources\Hr\Severance\Exits\Pages;

use App\Filament\Resources\Hr\Severance\Exits\EmployeeExitResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEmployeeExits extends ListRecords
{
    protected static string $resource = EmployeeExitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Registrar egreso'),
        ];
    }
}
