<?php

namespace App\Filament\Resources\PartnerCompanies\Pages;

use App\Filament\Resources\PartnerCompanies\PartnerCompanyResource;
use App\Models\PartnerCompany;
use App\Models\PartnerCompanyUser;
use App\Support\PartnerCompanyAlliedUsersFormSync;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditPartnerCompany extends EditRecord
{
    protected static string $resource = PartnerCompanyResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $record = $this->getRecord();
        if (! $record instanceof PartnerCompany) {
            return $data;
        }

        $data['partner_users'] = $record->partnerCompanyUsers()
            ->with('user')
            ->get()
            ->map(function (PartnerCompanyUser $link): array {
                return [
                    'user_id' => $link->user_id,
                    'name' => (string) ($link->user?->name ?? ''),
                    'email' => (string) ($link->user?->email ?? ''),
                    'password' => '',
                    'is_active' => (bool) ($link->user?->partner_user_is_active ?? true),
                ];
            })
            ->values()
            ->all();

        return $data;
    }

    protected function beforeValidate(): void
    {
        $record = $this->getRecord();
        if (! $record instanceof PartnerCompany) {
            return;
        }

        $rows = PartnerCompanyAlliedUsersFormSync::normalizeRows(
            $this->form->getRawState()['partner_users'] ?? [],
        );

        if ($rows !== []) {
            PartnerCompanyAlliedUsersFormSync::validateRowsForUpdate($record, $rows);
        }
    }

    protected function afterSave(): void
    {
        $record = $this->getRecord();
        if (! $record instanceof PartnerCompany) {
            return;
        }

        $rows = PartnerCompanyAlliedUsersFormSync::normalizeRows(
            $this->form->getRawState()['partner_users'] ?? [],
        );

        PartnerCompanyAlliedUsersFormSync::syncUsers($record->fresh(), $rows);
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
