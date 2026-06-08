<?php

namespace App\Filament\Resources\ConciliationCacheas\Pages;

use App\Filament\Resources\ConciliationCacheas\ConciliationCacheaResource;
use Filament\Resources\Pages\ViewRecord;

class ViewConciliationCachea extends ViewRecord
{
    protected static string $resource = ConciliationCacheaResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
