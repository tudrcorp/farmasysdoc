<?php

namespace App\Filament\Resources\Hr\Recruitment\Requests\Pages;

use App\Enums\RecruitmentRequestStatus;
use App\Filament\Resources\Hr\Recruitment\Requests\RecruitmentRequestResource;
use Filament\Resources\Pages\CreateRecord;

class CreateRecruitmentRequest extends CreateRecord
{
    protected static string $resource = RecruitmentRequestResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = RecruitmentRequestResource::currentUser();

        abort_unless(
            $user !== null && ($user->isAdministrator() || in_array((int) $data['branch_id'], $user->restrictedBranchIdsForQueries(), true)),
            403,
        );

        return [
            ...$data,
            'status' => RecruitmentRequestStatus::Pending,
            'requested_by' => $user->id,
        ];
    }

    protected function getRedirectUrl(): string
    {
        return RecruitmentRequestResource::getUrl('view', ['record' => $this->getRecord()]);
    }
}
