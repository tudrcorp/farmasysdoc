<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ClientResource;
use App\Services\Sales\ClientCommercialDiscountAssigner;
use Filament\Resources\Pages\CreateRecord;

class CreateClient extends CreateRecord
{
    protected static string $resource = ClientResource::class;

    protected static ?string $title = 'Crear Nuevo Cliente';

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $actor = auth()->user()?->email
            ?? auth()->user()?->name
            ?? 'sistema';

        $data['created_by'] = $actor;
        $data['updated_by'] = $actor;

        return $data;
    }

    protected function afterCreate(): void
    {
        $percent = (float) ($this->record->customer_discount ?? 0);
        if ($percent > 0.00001) {
            app(ClientCommercialDiscountAssigner::class)->assignIndividual($this->record, $percent);
        }
    }
}
