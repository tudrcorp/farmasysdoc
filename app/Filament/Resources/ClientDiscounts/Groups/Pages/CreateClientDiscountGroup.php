<?php

namespace App\Filament\Resources\ClientDiscounts\Groups\Pages;

use App\Filament\Resources\ClientDiscounts\Groups\ClientDiscountGroupResource;
use App\Models\ClientDiscountGroup;
use App\Services\Sales\ClientCommercialDiscountAssigner;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class CreateClientDiscountGroup extends CreateRecord
{
    protected static string $resource = ClientDiscountGroupResource::class;

    protected static ?string $title = 'Nuevo grupo de descuento';

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = Auth::id();
        $data['updated_by'] = Auth::id();

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        $clientIds = $data['client_ids'] ?? [];
        unset($data['client_ids']);

        /** @var ClientDiscountGroup $record */
        $record = static::getModel()::query()->create($data);

        app(ClientCommercialDiscountAssigner::class)->syncGroupClients(
            $record,
            is_array($clientIds) ? $clientIds : [],
        );

        return $record;
    }
}
