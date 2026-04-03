<?php

namespace App\Filament\Resources\ProductTransfers\Pages;

use App\Filament\Resources\ProductTransfers\ProductTransferResource;
use App\Filament\Resources\ProductTransfers\Schemas\ProductTransferForm;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditProductTransfer extends EditRecord
{
    protected static string $resource = ProductTransferResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $actor = auth()->user();
        $data['updated_by'] = $actor !== null
            ? (filled($actor->email) ? (string) $actor->email : (string) ($actor->name ?? 'usuario'))
            : 'sistema';

        return ProductTransferForm::enforceFromBranchForNonAdmin($data);
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
