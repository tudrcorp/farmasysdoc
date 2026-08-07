<?php

namespace App\Filament\Resources\ClientDiscounts\Groups\Pages;

use App\Filament\Resources\ClientDiscounts\Groups\ClientDiscountGroupResource;
use App\Services\Sales\ClientCommercialDiscountAssigner;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EditClientDiscountGroup extends EditRecord
{
    protected static string $resource = ClientDiscountGroupResource::class;

    protected static ?string $title = 'Editar grupo de descuento';

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['client_ids'] = $this->getRecord()->clients()->pluck('clients.id')->all();

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = Auth::id();

        return $data;
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $clientIds = $data['client_ids'] ?? [];
        unset($data['client_ids']);

        $record->update($data);

        app(ClientCommercialDiscountAssigner::class)->syncGroupClients(
            $record,
            is_array($clientIds) ? $clientIds : [],
        );

        return $record;
    }
}
