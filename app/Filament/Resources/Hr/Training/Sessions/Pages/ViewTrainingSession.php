<?php

namespace App\Filament\Resources\Hr\Training\Sessions\Pages;

use App\Filament\Resources\Hr\Training\Sessions\Actions\TrainingSessionActions;
use App\Filament\Resources\Hr\Training\Sessions\TrainingSessionResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewTrainingSession extends ViewRecord
{
    protected static string $resource = TrainingSessionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            TrainingSessionActions::enroll(),
            TrainingSessionActions::start(),
            TrainingSessionActions::finish(),
            TrainingSessionActions::cancel(),
            EditAction::make(),
        ];
    }
}
