<?php

namespace App\Filament\Resources\InventoryAdjustments\Pages;

use App\Filament\Resources\InventoryAdjustments\InventoryAdjustmentResource;
use Filament\Resources\Pages\ViewRecord;

class ViewInventoryAdjustment extends ViewRecord
{
    protected static string $resource = InventoryAdjustmentResource::class;

    public function getTitle(): string
    {
        $n = $this->getRecord()->getKey();

        return 'Ajuste #'.$n;
    }
}

