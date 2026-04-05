<?php

namespace App\Filament\Resources\PartnerCompanies\Pages;

use App\Filament\Resources\PartnerCompanies\PartnerCompanyResource;
use App\Models\PartnerCompany;
use App\Support\PartnerCompanyAlliedUsersFormSync;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreatePartnerCompany extends CreateRecord
{
    protected static string $resource = PartnerCompanyResource::class;

    protected function beforeValidate(): void
    {
        $rows = PartnerCompanyAlliedUsersFormSync::normalizeRows(
            $this->form->getRawState()['partner_users'] ?? [],
        );

        if ($rows !== []) {
            PartnerCompanyAlliedUsersFormSync::validateRowsForCreate($rows);
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $actor = Auth::user()?->email
            ?? Auth::user()?->name
            ?? 'sistema';

        $data['created_by'] = $actor;
        $data['updated_by'] = $actor;
        unset($data['code']);

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->update([
            'code' => PartnerCompany::formatCode($this->record->getKey()),
        ]);

        $this->record->refresh();

        $rows = PartnerCompanyAlliedUsersFormSync::normalizeRows(
            $this->form->getRawState()['partner_users'] ?? [],
        );

        if ($rows !== []) {
            PartnerCompanyAlliedUsersFormSync::createUsers($this->record, $rows);
        }
    }
}
