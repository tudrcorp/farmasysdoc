<?php

namespace App\Filament\Resources\Deliveries\Pages;

use App\Filament\Resources\Deliveries\DeliveryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ListDeliveries extends ListRecords
{
    protected static string $resource = DeliveryResource::class;

    public function getHeading(): string|Htmlable
    {
        return 'Entregas y logística';
    }

    public function getSubheading(): string|Htmlable|null
    {
        return 'Seguimiento de envíos vinculados a pedidos, estados y responsables.';
    }

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Registrar entrega')
                ->icon(Heroicon::Plus)
                ->extraAttributes([
                    'class' => 'farmadoc-ios-action farmadoc-ios-action--primary',
                ]),
        ];
    }
}
