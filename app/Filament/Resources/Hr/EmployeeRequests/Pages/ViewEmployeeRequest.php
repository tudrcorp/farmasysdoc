<?php

namespace App\Filament\Resources\Hr\EmployeeRequests\Pages;

use App\Filament\Resources\Hr\EmployeeRequests\Actions\EmployeeRequestDecisionActions;
use App\Filament\Resources\Hr\EmployeeRequests\EmployeeRequestResource;
use Filament\Resources\Pages\ViewRecord;

class ViewEmployeeRequest extends ViewRecord
{
    protected static string $resource = EmployeeRequestResource::class;

    protected static ?string $title = 'Detalle de la solicitud';

    protected function getHeaderActions(): array
    {
        return [
            EmployeeRequestDecisionActions::approve(),
            EmployeeRequestDecisionActions::reject(),
            EmployeeRequestDecisionActions::downloadCertificate(),
        ];
    }
}
