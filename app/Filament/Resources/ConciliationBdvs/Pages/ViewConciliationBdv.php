<?php

namespace App\Filament\Resources\ConciliationBdvs\Pages;

use App\Filament\Resources\ConciliationBdvs\ConciliationBdvResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;

class ViewConciliationBdv extends ViewRecord
{
    protected static string $resource = ConciliationBdvResource::class;

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }
}
