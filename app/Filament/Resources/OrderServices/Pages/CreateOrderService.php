<?php

namespace App\Filament\Resources\OrderServices\Pages;

use App\Filament\Resources\OrderServices\OrderServiceResource;
use App\Models\OrderService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateOrderService extends CreateRecord
{
    protected static string $resource = OrderServiceResource::class;

    protected static ?string $title = 'Crear Orden de Servicio';

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        /**
         * La columna es obligatoria y única: placeholder hasta obtener el id en afterCreate.
         */
        $data['service_order_number'] = 'ORD-TEMP-'.Str::uuid()->toString();

        return $data;
    }

    protected function afterCreate(): void
    {
        $this->record->update([
            'service_order_number' => OrderService::formatServiceOrderNumber($this->record->getKey()),
        ]);
    }
}
