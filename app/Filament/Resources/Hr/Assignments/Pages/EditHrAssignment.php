<?php

namespace App\Filament\Resources\Hr\Assignments\Pages;

use App\Filament\Resources\Hr\Assignments\HrAssignmentResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditHrAssignment extends EditRecord
{
    protected static string $resource = HrAssignmentResource::class;

    protected static ?string $title = 'Editar asignación';

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
