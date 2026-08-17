<?php

namespace App\Filament\Resources\PosTerminals\Pages;

use App\Filament\Resources\PosTerminals\PosTerminalResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListPosTerminals extends ListRecords
{
    protected static string $resource = PosTerminalResource::class;

    protected static ?string $title = 'Puntos de venta';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo punto de venta')
                ->icon(Heroicon::Plus)
                ->color('primary')
                ->extraAttributes([
                    'class' => 'farmadoc-ios-action farmadoc-ios-action--primary',
                ]),
        ];
    }
}
