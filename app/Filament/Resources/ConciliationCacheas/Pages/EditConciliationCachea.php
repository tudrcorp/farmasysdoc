<?php

namespace App\Filament\Resources\ConciliationCacheas\Pages;

use App\Filament\Resources\ConciliationCacheas\ConciliationCacheaResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;

class EditConciliationCachea extends EditRecord
{
    protected static string $resource = ConciliationCacheaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
            DeleteAction::make(),
        ];
    }
}
