<?php

namespace App\Filament\Resources\ProductTransfers\Pages;

use App\Filament\Resources\ProductTransfers\ProductTransferResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListProductTransfers extends ListRecords
{
    protected static string $resource = ProductTransferResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->icon(Heroicon::Plus)
                ->extraAttributes([
                    'class' => 'farmadoc-ios-action farmadoc-ios-action--primary',
                ]),
        ];
    }
}
