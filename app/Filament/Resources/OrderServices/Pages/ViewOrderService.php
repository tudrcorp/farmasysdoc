<?php

namespace App\Filament\Resources\OrderServices\Pages;

use App\Filament\Resources\OrderServices\OrderServiceResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewOrderService extends ViewRecord
{
    protected static string $resource = OrderServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver')
                ->icon(Heroicon::ArrowLeft)
                ->color('gray')
                ->extraAttributes([
                    'class' => 'farmadoc-ios-action farmadoc-ios-action--gray',
                ])
                ->url(route('filament.farmaadmin.resources.order-services.index')),
        ];
    }
}
