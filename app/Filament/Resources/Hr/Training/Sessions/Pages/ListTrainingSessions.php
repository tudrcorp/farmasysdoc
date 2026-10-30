<?php

namespace App\Filament\Resources\Hr\Training\Sessions\Pages;

use App\Filament\Resources\Hr\Training\Sessions\TrainingSessionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTrainingSessions extends ListRecords
{
    protected static string $resource = TrainingSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Programar capacitación'),
        ];
    }
}
