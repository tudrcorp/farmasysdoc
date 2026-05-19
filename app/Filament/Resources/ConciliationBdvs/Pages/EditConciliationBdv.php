<?php

namespace App\Filament\Resources\ConciliationBdvs\Pages;

use App\Filament\Resources\ConciliationBdvs\ConciliationBdvResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditConciliationBdv extends EditRecord
{
    protected static string $resource = ConciliationBdvResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
