<?php

namespace App\Filament\Resources\Hr\Severance\Exits\Pages;

use App\Filament\Resources\Hr\Severance\Exits\Actions\EmployeeExitActions;
use App\Filament\Resources\Hr\Severance\Exits\EmployeeExitResource;
use Filament\Resources\Pages\ViewRecord;

class ViewEmployeeExit extends ViewRecord
{
    protected static string $resource = EmployeeExitResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EmployeeExitActions::checklist(),
            EmployeeExitActions::createSettlement(),
            EmployeeExitActions::viewSettlement(),
            EmployeeExitActions::cancel(),
        ];
    }
}
