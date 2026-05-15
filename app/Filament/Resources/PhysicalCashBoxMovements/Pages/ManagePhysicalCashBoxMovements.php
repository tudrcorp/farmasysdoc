<?php

namespace App\Filament\Resources\PhysicalCashBoxMovements\Pages;

use App\Filament\Resources\PhysicalCashBoxMovements\PhysicalCashBoxMovementResource;
use Filament\Resources\Pages\ManageRecords;

class ManagePhysicalCashBoxMovements extends ManageRecords
{
    protected static string $resource = PhysicalCashBoxMovementResource::class;

    protected static ?string $title = 'Movimientos de caja física';

    protected function getHeaderActions(): array
    {
        return [];
    }
}
