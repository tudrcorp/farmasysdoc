<?php

namespace App\Filament\Resources\Orders\Pages;

use App\Enums\ConvenioType;
use App\Filament\Resources\Orders\OrderResource;
use App\Models\User;
use App\Support\Orders\PartnerOrderDeliverySync;
use App\Support\Orders\PartnerOrderFormMutator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateOrder extends CreateRecord
{
    protected static string $resource = OrderResource::class;

    protected function afterCreate(): void
    {
        $record = $this->getRecord();
        $record->assignCanonicalOrderNumber();
        $record->recalculateTotalsFromItems();
        PartnerOrderDeliverySync::sync($record, auth()->user());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $actor = auth()->user()?->email
            ?? auth()->user()?->name
            ?? 'sistema';

        $data['created_by'] = $actor;
        $data['updated_by'] = $actor;
        $data['order_number'] = 'TMP-'.Str::lower(Str::ulid());
        $data['convenio_type'] = $data['convenio_type'] ?? ConvenioType::Particular->value;

        $user = auth()->user();
        if ($user instanceof User && $user->isPartnerCompanyUser() && ! $user->isAdministrator()) {
            $data['partner_company_id'] = (int) $user->partner_company_id;
            $data['client_id'] = null;
        } elseif ($user instanceof User && $user->isAdministrator()) {
            if (! empty($data['partner_company_id'] ?? null)) {
                $data['client_id'] = null;
            } else {
                $data['partner_company_id'] = null;
            }
        } else {
            $data['partner_company_id'] = null;
        }

        $data = PartnerOrderFormMutator::sanitizeForSave($data, $user);

        return collect($data)->except(['items'])->all();
    }
}
