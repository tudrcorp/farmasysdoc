<?php

namespace App\Filament\Resources\FefoPosAlertLogs\Pages;

use App\Filament\Resources\FefoPosAlertLogs\FefoPosAlertLogResource;
use Filament\Resources\Pages\ViewRecord;

class ViewFefoPosAlertLog extends ViewRecord
{
    protected static string $resource = FefoPosAlertLogResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
