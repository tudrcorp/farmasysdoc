<?php

namespace App\Filament\Resources\Hr\Training\Courses\Pages;

use App\Filament\Resources\Hr\Training\Courses\TrainingCourseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListTrainingCourses extends ListRecords
{
    protected static string $resource = TrainingCourseResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nuevo curso'),
        ];
    }
}
