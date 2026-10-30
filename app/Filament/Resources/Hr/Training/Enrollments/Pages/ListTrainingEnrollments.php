<?php

namespace App\Filament\Resources\Hr\Training\Enrollments\Pages;

use App\Filament\Resources\Hr\Training\Enrollments\TrainingEnrollmentResource;
use Filament\Resources\Pages\ListRecords;

class ListTrainingEnrollments extends ListRecords
{
    protected static string $resource = TrainingEnrollmentResource::class;
}
