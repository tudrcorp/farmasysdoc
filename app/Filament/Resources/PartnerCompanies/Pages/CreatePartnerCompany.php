<?php

namespace App\Filament\Resources\PartnerCompanies\Pages;

use App\Filament\Resources\PartnerCompanies\PartnerCompanyResource;
use App\Filament\Resources\PartnerCompanies\Support\InitialAgreementDraftNotice;
use App\Models\PartnerCompany;
use App\Models\User;
use App\Support\PartnerCompanyAlliedUsersFormSync;
use App\Support\Partners\PartnerCompanyCreditTerms;
use App\Support\Partners\PartnerUncoveredMedicineToken;
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
        $authUser = Auth::user();

        $actor = $authUser?->email
            ?? $authUser?->name
            ?? 'sistema';

        $data['created_by'] = $actor;
        $data['updated_by'] = $actor;

        if ($authUser instanceof User && $authUser->isManager() && ! $authUser->isAdministrator()) {
            $data['is_active'] = false;
        }

        if (! ($authUser instanceof User && $authUser->isAdministrator())) {
            $data['can_place_orders'] = false;
        }

        unset($data['code'], $data['uncovered_medicines_token']);

        return PartnerCompanyCreditTerms::normalize($data, isCreating: true);
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

        InitialAgreementDraftNotice::syncAndNotify($this->record);

        if ($this->record instanceof PartnerCompany) {
            PartnerUncoveredMedicineToken::store(
                $this->record,
                $this->form->getRawState()['uncovered_medicines_token'] ?? null,
            );
        }
    }
}
