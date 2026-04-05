<?php

namespace App\Filament\BusinessPartners\Resources\HistoricalOfMovements\Pages;

use App\Filament\BusinessPartners\Resources\HistoricalOfMovements\HistoricalOfMovementResource;
use App\Models\HistoricalOfMovement;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewHistoricalOfMovement extends ViewRecord
{
    protected static string $resource = HistoricalOfMovementResource::class;

    public function getTitle(): string|Htmlable
    {
        $record = $this->getRecord();
        if ($record instanceof HistoricalOfMovement) {
            $record->loadMissing('order');
            $orderNumber = $record->order?->order_number;
            if (filled($orderNumber)) {
                return 'Movimiento de crédito · '.$orderNumber;
            }
        }

        return parent::getTitle();
    }
}
