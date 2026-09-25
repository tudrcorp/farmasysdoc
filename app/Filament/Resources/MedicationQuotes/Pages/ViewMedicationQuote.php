<?php

namespace App\Filament\Resources\MedicationQuotes\Pages;

use App\Filament\Resources\MedicationQuotes\MedicationQuoteResource;
use App\Models\MedicationQuote;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Js;

class ViewMedicationQuote extends ViewRecord
{
    protected static string $resource = MedicationQuoteResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('downloadPdf')
                ->label('Imprimir PDF')
                ->icon(Heroicon::ArrowDownTray)
                ->action(function (): void {
                    $record = $this->getRecord();
                    if (! $record instanceof MedicationQuote) {
                        return;
                    }

                    $url = URL::temporarySignedRoute(
                        'medication-quotes.pdf',
                        now()->addMinutes(10),
                        ['medicationQuote' => $record->getKey()],
                    );
                    $this->js('window.open('.Js::from($url).', "_blank")');
                }),
            EditAction::make(),
        ];
    }
}
