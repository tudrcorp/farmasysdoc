<?php

namespace App\Filament\Resources\AuditLogs\Pages;

use App\Filament\Resources\AuditLogs\AuditLogResource;
use Filament\Resources\Pages\ListRecords;

class ListAuditLogs extends ListRecords
{
    protected static string $resource = AuditLogResource::class;

    protected static ?string $title = 'Auditoría y trazas de usuarios';

    protected function getHeaderActions(): array
    {
        return [];
    }
}
