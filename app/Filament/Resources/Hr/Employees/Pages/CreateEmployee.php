<?php

namespace App\Filament\Resources\Hr\Employees\Pages;

use App\Filament\Resources\Hr\Employees\EmployeeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEmployee extends CreateRecord
{
    protected static string $resource = EmployeeResource::class;

    protected static ?string $title = 'Nuevo empleado';
}
