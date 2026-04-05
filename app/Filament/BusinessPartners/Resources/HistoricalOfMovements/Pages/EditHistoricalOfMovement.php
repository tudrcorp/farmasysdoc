<?php

namespace App\Filament\BusinessPartners\Resources\HistoricalOfMovements\Pages;

use App\Filament\BusinessPartners\Resources\HistoricalOfMovements\HistoricalOfMovementResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditHistoricalOfMovement extends EditRecord
{
    protected static string $resource = HistoricalOfMovementResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
