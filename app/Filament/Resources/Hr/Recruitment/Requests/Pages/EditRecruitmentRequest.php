<?php

namespace App\Filament\Resources\Hr\Recruitment\Requests\Pages;

use App\Filament\Resources\Hr\Recruitment\Requests\RecruitmentRequestResource;
use Filament\Resources\Pages\EditRecord;

class EditRecruitmentRequest extends EditRecord
{
    protected static string $resource = RecruitmentRequestResource::class;

    protected function getRedirectUrl(): string
    {
        return RecruitmentRequestResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
