<?php

namespace App\Filament\Resources\Hr\Training\Courses\Pages;

use App\Filament\Resources\Hr\Training\Courses\TrainingCourseResource;
use Filament\Resources\Pages\EditRecord;

class EditTrainingCourse extends EditRecord
{
    protected static string $resource = TrainingCourseResource::class;

    protected function getRedirectUrl(): string
    {
        return TrainingCourseResource::getUrl('index');
    }
}
