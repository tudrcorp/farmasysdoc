<?php

namespace App\Filament\Resources\Rols\Pages;

use App\Filament\Resources\Rols\RolResource;
use App\Filament\Resources\Rols\Schemas\RolForm;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditRol extends EditRecord
{
    protected static string $resource = RolResource::class;

    protected static ?string $title = 'Editar rol';

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return RolForm::hydrateGroupedPermissions($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        return RolForm::collapseGroupedPermissions($data);
    }
}
