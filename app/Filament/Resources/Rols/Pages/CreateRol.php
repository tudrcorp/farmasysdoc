<?php

namespace App\Filament\Resources\Rols\Pages;

use App\Filament\Resources\Rols\RolResource;
use App\Filament\Resources\Rols\Schemas\RolForm;
use Filament\Resources\Pages\CreateRecord;

class CreateRol extends CreateRecord
{
    protected static string $resource = RolResource::class;

    protected static ?string $title = 'Nuevo rol';

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return RolForm::collapseGroupedPermissions($data);
    }
}
