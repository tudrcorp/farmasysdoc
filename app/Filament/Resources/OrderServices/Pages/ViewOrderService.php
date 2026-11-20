<?php

namespace App\Filament\Resources\OrderServices\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ReviewOrderAcceptance;
use App\Filament\Resources\OrderServices\OrderServiceResource;
use App\Models\Order;
use App\Models\OrderService;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewOrderService extends ViewRecord
{
    protected static string $resource = OrderServiceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('reviewAcceptance')
                ->label('Ver detalle del Pedido')
                ->icon(Heroicon::ClipboardDocumentList)
                ->color('primary')
                ->visible(function (): bool {
                    $service = $this->getRecord();

                    if (! $service instanceof OrderService || $service->order_id === null) {
                        return false;
                    }

                    $order = $service->order;

                    return $order instanceof Order && ReviewOrderAcceptance::canOpen($order);
                })
                ->url(function (): string {
                    $service = $this->getRecord();

                    return OrderResource::getUrl('acceptance', ['record' => $service instanceof OrderService ? $service->order_id : null]);
                }),
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
