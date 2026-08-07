<?php

namespace App\Filament\Resources\Clients\Pages;

use App\Filament\Resources\Clients\ClientResource;
use App\Services\Sales\ClientCommercialDiscountAssigner;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditClient extends EditRecord
{
    protected static string $resource = ClientResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['updated_by'] = auth()->user()?->email
            ?? auth()->user()?->name
            ?? 'sistema';

        return $data;
    }

    protected function afterSave(): void
    {
        $percent = (float) ($this->record->customer_discount ?? 0);
        if ($percent > 0.00001) {
            app(ClientCommercialDiscountAssigner::class)->assignIndividual($this->record, $percent);
        }
    }

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
