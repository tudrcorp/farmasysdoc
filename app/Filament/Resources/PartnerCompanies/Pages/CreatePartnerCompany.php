<?php

namespace App\Filament\Resources\PartnerCompanies\Pages;

use App\Filament\Resources\PartnerCompanies\PartnerCompanyResource;
use App\Models\PartnerCompany;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;

class CreatePartnerCompany extends CreateRecord
{
    protected static string $resource = PartnerCompanyResource::class;

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
    }
}
