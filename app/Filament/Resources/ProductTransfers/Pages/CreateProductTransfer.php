<?php

namespace App\Filament\Resources\ProductTransfers\Pages;

use App\Filament\Resources\ProductTransfers\ProductTransferResource;
use App\Filament\Resources\ProductTransfers\Schemas\ProductTransferForm;
use App\Models\ProductTransfer;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateProductTransfer extends CreateRecord
{
    protected static string $resource = ProductTransferResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $actor = auth()->user();
        $label = $actor !== null
            ? (filled($actor->email) ? (string) $actor->email : (string) ($actor->name ?? 'usuario'))
            : 'sistema';
        $data['created_by'] = $label;
        $data['updated_by'] = $label;
        $data['code'] = 'PENDING-'.str_replace('-', '', (string) Str::uuid());

        return ProductTransferForm::enforceFromBranchForNonAdmin($data);
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();
        $record->forceFill([
            'code' => ProductTransfer::automaticCodeForId((int) $record->getKey()),
        ])->save();
    }
}
