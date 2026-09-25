<?php

namespace App\Filament\Resources\MedicationQuotes\Pages;

use App\Filament\Resources\MedicationQuotes\MedicationQuoteResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMedicationQuotes extends ListRecords
{
    protected static string $resource = MedicationQuoteResource::class;

    protected static ?string $title = 'Cotizador de medicamentos';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Nueva cotización'),
        ];
    }
}
