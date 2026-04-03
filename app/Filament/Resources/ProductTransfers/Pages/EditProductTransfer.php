<?php

namespace App\Filament\Resources\ProductTransfers\Pages;

use App\Filament\Resources\ProductTransfers\ProductTransferResource;
use App\Filament\Resources\ProductTransfers\Schemas\ProductTransferForm;
use App\Models\ProductTransfer;
use App\Services\Inventory\ProductTransferCompletionService;
use App\Support\ProductTransferStockValidator;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class EditProductTransfer extends EditRecord
{
    protected static string $resource = ProductTransferResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        /** @var ProductTransfer $record */
        $record = $this->getRecord();

        if (ProductTransferForm::isReceivingBranchUser($record)) {
            $data['from_branch_id'] = $record->from_branch_id;
            $data['to_branch_id'] = $record->to_branch_id;
            $data['transfer_type'] = $record->transfer_type;
        }

        $willComplete = ($data['status'] ?? '') === 'completed' && $record->status !== 'completed';

        if ($willComplete) {
            $svc = app(ProductTransferCompletionService::class);
            if (! $svc->userMayMarkCompleted(auth()->user(), $record)) {
                throw ValidationException::withMessages([
                    'data.status' => 'Solo el personal de la sucursal destino o un administrador puede marcar el traslado como completado.',
                ]);
            }
        }

        $fromId = (int) ($data['from_branch_id'] ?? $record->from_branch_id);
        if (! $willComplete) {
            ProductTransferStockValidator::assertSufficientStockAtBranch($fromId, $data['items'] ?? []);
        }

        $actor = auth()->user();
        $data['updated_by'] = $actor !== null
            ? (filled($actor->email) ? (string) $actor->email : (string) ($actor->name ?? 'usuario'))
            : 'sistema';

        if (! ProductTransferForm::isReceivingBranchUser($record)) {
            return ProductTransferForm::enforceFromBranchForNonAdmin($data);
        }

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof ProductTransfer) {
            return parent::handleRecordUpdate($record, $data);
        }

        $wasCompleted = $record->status === 'completed';
        $willComplete = ($data['status'] ?? '') === 'completed';

        $except = ['items'];
        if ($willComplete && ! $wasCompleted) {
            $except = array_merge($except, [
                'status',
                'total_transfer_cost',
                'completed_by',
                'completed_at',
                'sale_id',
            ]);
        }

        $record->update(Arr::except($data, $except));

        if ($willComplete && ! $wasCompleted) {
            app(ProductTransferCompletionService::class)->complete(
                $record->fresh(['items.product']),
                auth()->user(),
            );
        }

        return $record->fresh();
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
