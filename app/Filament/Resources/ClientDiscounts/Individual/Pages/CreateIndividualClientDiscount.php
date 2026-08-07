<?php

namespace App\Filament\Resources\ClientDiscounts\Individual\Pages;

use App\Filament\Resources\ClientDiscounts\Individual\IndividualClientDiscountResource;
use App\Models\Client;
use App\Services\Sales\ClientCommercialDiscountAssigner;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateIndividualClientDiscount extends CreateRecord
{
    protected static string $resource = IndividualClientDiscountResource::class;

    protected static ?string $title = 'Asociar descuento individual';

    protected function handleRecordCreation(array $data): Model
    {
        $clientId = (int) ($data['client_id'] ?? 0);
        $percent = (float) ($data['customer_discount'] ?? 0);

        $client = Client::query()->findOrFail($clientId);

        app(ClientCommercialDiscountAssigner::class)->assignIndividual($client, $percent);

        return $client->refresh();
    }

    protected function getRedirectUrl(): string
    {
        return $this->getResource()::getUrl('index');
    }
}
