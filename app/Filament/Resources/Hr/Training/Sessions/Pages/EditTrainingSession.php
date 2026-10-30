<?php

namespace App\Filament\Resources\Hr\Training\Sessions\Pages;

use App\Filament\Resources\Hr\Training\Sessions\TrainingSessionResource;
use Filament\Resources\Pages\EditRecord;

class EditTrainingSession extends EditRecord
{
    protected static string $resource = TrainingSessionResource::class;

    protected function getRedirectUrl(): string
    {
        return TrainingSessionResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
