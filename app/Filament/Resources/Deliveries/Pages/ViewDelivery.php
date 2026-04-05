<?php

namespace App\Filament\Resources\Deliveries\Pages;

use App\Filament\Resources\Deliveries\DeliveryResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewDelivery extends ViewRecord
{
    protected static string $resource = DeliveryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('Editar')
                ->icon(Heroicon::PencilSquare)
                ->extraAttributes([
                    'class' => 'farmadoc-ios-action farmadoc-ios-action--primary',
                ]),
        ];
    }
}
