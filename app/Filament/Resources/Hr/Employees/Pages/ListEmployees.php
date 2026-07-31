<?php

namespace App\Filament\Resources\Hr\Employees\Pages;

use App\Filament\Resources\Hr\Employees\EmployeeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListEmployees extends ListRecords
{
    protected static string $resource = EmployeeResource::class;

    protected static ?string $title = 'Empleados';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo empleado')
                ->icon(Heroicon::Plus),
        ];
    }
}
