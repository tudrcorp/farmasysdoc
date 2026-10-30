<?php

namespace App\Filament\Resources\Hr\Recruitment\Candidates\Pages;

use App\Filament\Resources\Hr\Recruitment\Candidates\RecruitmentCandidateResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRecruitmentCandidate extends CreateRecord
{
    protected static string $resource = RecruitmentCandidateResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        return [
            ...$data,
            'reviewed_at' => now(),
            'reviewed_by' => RecruitmentCandidateResource::currentUser()?->id,
        ];
    }

    protected function getRedirectUrl(): string
    {
        return RecruitmentCandidateResource::getUrl('index');
    }
}
