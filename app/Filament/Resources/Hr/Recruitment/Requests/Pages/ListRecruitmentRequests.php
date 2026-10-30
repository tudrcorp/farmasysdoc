<?php

namespace App\Filament\Resources\Hr\Recruitment\Requests\Pages;

use App\Filament\Resources\Hr\Recruitment\Requests\RecruitmentRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRecruitmentRequests extends ListRecords
{
    protected static string $resource = RecruitmentRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nueva solicitud'),
        ];
    }
}
