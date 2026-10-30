<?php

namespace App\Filament\Resources\Hr\Recruitment\Applications\Pages;

use App\Filament\Resources\Hr\Recruitment\Applications\Actions\RecruitmentApplicationActions;
use App\Filament\Resources\Hr\Recruitment\Applications\RecruitmentApplicationResource;
use Filament\Resources\Pages\ViewRecord;

class ViewRecruitmentApplication extends ViewRecord
{
    protected static string $resource = RecruitmentApplicationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RecruitmentApplicationActions::hire(),
            RecruitmentApplicationActions::moveStage(),
            RecruitmentApplicationActions::evaluate(),
            RecruitmentApplicationActions::addNote(),
            RecruitmentApplicationActions::discard(),
        ];
    }
}
