<?php

namespace App\Filament\Resources\ClientDiscounts\Individual\Pages;

use App\Filament\Resources\ClientDiscounts\Individual\IndividualClientDiscountResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListIndividualClientDiscounts extends ListRecords
{
    protected static string $resource = IndividualClientDiscountResource::class;

    protected static ?string $title = 'Descuentos individuales';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Asociar cliente')
                ->icon(Heroicon::Plus)
                ->color('primary')
                ->extraAttributes([
                    'class' => 'farmadoc-ios-action farmadoc-ios-action--primary',
                ]),
        ];
    }
}
