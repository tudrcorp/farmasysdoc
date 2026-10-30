<?php

namespace App\Filament\Resources\Hr\Training\Sessions\Pages;

use App\Filament\Resources\Hr\Training\Sessions\TrainingSessionResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTrainingSession extends CreateRecord
{
    protected static string $resource = TrainingSessionResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [...$data, 'created_by' => TrainingSessionResource::currentUser()?->id];
    }

    protected function getRedirectUrl(): string
    {
        return TrainingSessionResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
