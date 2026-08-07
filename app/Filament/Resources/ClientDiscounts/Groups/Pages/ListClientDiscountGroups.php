<?php

namespace App\Filament\Resources\ClientDiscounts\Groups\Pages;

use App\Filament\Resources\ClientDiscounts\Groups\ClientDiscountGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListClientDiscountGroups extends ListRecords
{
    protected static string $resource = ClientDiscountGroupResource::class;

    protected static ?string $title = 'Grupos de descuento';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo grupo')
                ->icon(Heroicon::Plus)
                ->color('primary')
                ->extraAttributes([
                    'class' => 'farmadoc-ios-action farmadoc-ios-action--primary',
                ]),
        ];
    }
}
