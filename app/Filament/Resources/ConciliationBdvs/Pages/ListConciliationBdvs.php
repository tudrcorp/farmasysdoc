<?php

namespace App\Filament\Resources\ConciliationBdvs\Pages;

use App\Filament\Resources\ConciliationBdvs\ConciliationBdvResource;
use Filament\Resources\Pages\ListRecords;

class ListConciliationBdvs extends ListRecords
{
    protected static string $resource = ConciliationBdvResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
