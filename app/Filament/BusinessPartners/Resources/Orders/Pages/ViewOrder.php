<?php

namespace App\Filament\BusinessPartners\Resources\Orders\Pages;

use App\Filament\BusinessPartners\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Actions\DownloadPartnerReceiptAction;
use App\Filament\Resources\Orders\Tables\OrdersTable;
use App\Models\Order;
use App\Models\OrderAcceptance;
use App\Models\OrderItem;
use App\Services\Orders\OrderAcceptanceNotePdfFactory;
use App\Services\Orders\PartnerOrderReceiptSync;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewOrder extends ViewRecord
{
    protected static string $resource = OrderResource::class;

    public function mount(int|string $record): void
    {
        parent::mount($record);

        $order = $this->getRecord();
        if (! $order instanceof Order) {
            return;
        }

        $order->load(['items.product', 'acceptance.items']);
        app(PartnerOrderReceiptSync::class)->refreshOrder($order);
        $order->setRelation(
            'items',
            $order->items->each(function (OrderItem $item) use ($order): void {
                $item->setRelation('order', $order);
            }),
        );
    }

    protected function getHeaderActions(): array
    {
        return [
            DownloadPartnerReceiptAction::make(),
            Action::make('downloadAcceptanceNote')
                ->label('Descargar nota de entrega')
                ->icon(Heroicon::ArrowDownTray)
                ->color('gray')
                ->visible(function (): bool {
                    $order = $this->getRecord();

                    return $order instanceof Order && $order->acceptance instanceof OrderAcceptance;
                })
                ->action(function () {
                    $order = $this->getRecord();

                    if (! $order instanceof Order || ! $order->acceptance instanceof OrderAcceptance) {
                        return null;
                    }

                    return app(OrderAcceptanceNotePdfFactory::class)->stream($order->acceptance);
                }),
            OrdersTable::partnerRateDeliveryServiceAction()
                ->label('Calificar servicio de entrega'),
            EditAction::make()
                ->visible(fn (): bool => static::getResource()::canEdit($this->getRecord())),
        ];
    }
}
