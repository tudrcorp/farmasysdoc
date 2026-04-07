<?php

namespace App\Filament\Resources\ProductTransfers\Pages;

use App\Enums\ProductTransferStatus;
use App\Filament\Resources\ProductTransfers\ProductTransferResource;
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

        $willComplete = ($data['status'] ?? '') === ProductTransferStatus::Completed->value
            && ! ProductTransferStatus::isCompletedValue($record->status);

        if ($willComplete && $record->status !== ProductTransferStatus::InProgress) {
            throw ValidationException::withMessages([
                'data.status' => 'Solo puede marcarse «Completado» un traslado que esté «En proceso». Use la acción «Marcar completado» en el listado o la vista del traslado.',
            ]);
        }

        if ($willComplete) {
            $svc = app(ProductTransferCompletionService::class);
            if (! $svc->userMayMarkCompleted(auth()->user(), $record)) {
                throw ValidationException::withMessages([
                    'data.status' => 'Solo el personal de la sucursal destino o un administrador puede completar el traslado.',
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

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof ProductTransfer) {
            return parent::handleRecordUpdate($record, $data);
        }

        $wasCompleted = ProductTransferStatus::isCompletedValue($record->status);
        $willComplete = ($data['status'] ?? '') === ProductTransferStatus::Completed->value;

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
