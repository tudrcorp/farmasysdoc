<?php

namespace App\Filament\Resources\Hr\Recruitment\Requests\Pages;

use App\Filament\Resources\Hr\Recruitment\Requests\Actions\RecruitmentRequestDecisionActions;
use App\Filament\Resources\Hr\Recruitment\Requests\RecruitmentRequestResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewRecruitmentRequest extends ViewRecord
{
    protected static string $resource = RecruitmentRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RecruitmentRequestDecisionActions::approve(),
            RecruitmentRequestDecisionActions::reject(),
            RecruitmentRequestDecisionActions::cancel(),
            EditAction::make(),
        ];
    }
}
