<?php

namespace App\Filament\Resources\Hr\Assignments\Pages;

use App\Filament\Resources\Hr\Assignments\HrAssignmentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListHrAssignments extends ListRecords
{
    protected static string $resource = HrAssignmentResource::class;

    protected static ?string $title = 'Asignaciones';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nueva asignación')
                ->icon(Heroicon::Plus),
        ];
    }
}
