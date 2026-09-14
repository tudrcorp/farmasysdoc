<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\Concerns\HasFarmaadminIosProductPage;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use App\Support\Products\ProductDeletion;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\QueryException;

class EditProduct extends EditRecord
{
    use HasFarmaadminIosProductPage;

    protected static string $resource = ProductResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->user()?->email
            ?? auth()->user()?->name
            ?? 'sistema';

        if (blank($data['barcode'] ?? null) && $this->record?->id !== null) {
            $data['barcode'] = '00'.$this->record->id;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make()
                ->using(function (DeleteAction $action, Product $record): bool {
                    try {
                        return (bool) $record->delete();
                    } catch (QueryException $exception) {
                        if (! ProductDeletion::isRestrictForeignKeyViolation($exception)) {
                            throw $exception;
                        }

                        Notification::make()
                            ->title('No se puede eliminar el producto')
                            ->body('Tiene inventario, ventas, compras u otros registros asociados. Desactívalo en su lugar.')
                            ->danger()
                            ->send();

                        $action->halt();

                        return false;
                    }
                }),
        ];
    }
}
