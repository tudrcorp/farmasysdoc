<?php

namespace App\Filament\BusinessPartners\Resources\Orders\Pages;

use App\Filament\BusinessPartners\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Models\OrderItem;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

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

        $order->load(['items.product']);
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
            EditAction::make()
                ->visible(fn (): bool => static::getResource()::canEdit($this->getRecord())),
        ];
    }
}
