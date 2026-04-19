<?php

namespace App\Filament\Resources\ProductTransfers\Pages;

use App\Enums\ProductTransferStatus;
use App\Filament\Resources\ProductTransfers\ProductTransferResource;
use App\Filament\Resources\ProductTransfers\Schemas\ProductTransferForm;
use App\Models\ProductTransfer;
use App\Models\User;
use App\Support\ProductTransfers\NotifyAdministratorsOnManagerTransferRequested;
use App\Support\ProductTransferStockValidator;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Throwable;

class CreateProductTransfer extends CreateRecord
{
    protected static string $resource = ProductTransferResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $actor = Auth::user();
        $label = $actor !== null
            ? (filled($actor->email) ? (string) $actor->email : (string) ($actor->name ?? 'usuario'))
            : 'sistema';

        $data['status'] = ProductTransferStatus::Pending->value;

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

        return ProductTransferForm::enforceToBranchForRequestingBranch($merged);
    }

    protected function afterCreate(): void
    {
        $record = $this->getRecord();
        $record->forceFill([
            'code' => ProductTransfer::automaticCodeForId((int) $record->getKey()),
        ])->save();

        try {
            $actor = Auth::user();
            app(NotifyAdministratorsOnManagerTransferRequested::class)->notify(
                $record->fresh(['items.product', 'fromBranch', 'toBranch']),
                $actor instanceof User ? $actor : null,
            );
        } catch (Throwable $exception) {
            Log::warning('No se pudo enviar WhatsApp de traslado registrado', [
                'transfer_id' => $record->id,
                'error' => $exception->getMessage(),
            ]);
        }
    }
}
