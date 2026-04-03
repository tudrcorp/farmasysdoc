<?php

namespace App\Filament\Resources\ProductTransfers\Pages;

use App\Filament\Resources\ProductTransfers\ProductTransferResource;
use App\Filament\Resources\ProductTransfers\Schemas\ProductTransferForm;
use App\Models\ProductTransfer;
use App\Support\ProductTransferStockValidator;
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

        $fromBranchId = (int) ($data['from_branch_id'] ?? 0);
        ProductTransferStockValidator::assertSufficientStockAtBranch($fromBranchId, $data['items'] ?? []);

        $merged = array_merge(
            collect($data)->except(['items'])->all(),
            [
                'created_by' => $label,
                'updated_by' => $label,
                'code' => 'PENDING-'.str_replace('-', '', (string) Str::uuid()),
            ],
        );

        return ProductTransferForm::enforceFromBranchForNonAdmin($merged);
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();
        $record->forceFill([
            'code' => ProductTransfer::automaticCodeForId((int) $record->getKey()),
        ])->save();
    }
}
