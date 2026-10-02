<?php

namespace App\Filament\Resources\FiscalDocuments\Pages;

use App\Filament\Resources\FiscalDocuments\Actions\FiscalDocumentActions;
use App\Filament\Resources\FiscalDocuments\FiscalDocumentResource;
use Filament\Resources\Pages\ViewRecord;

class ViewFiscalDocument extends ViewRecord
{
    protected static string $resource = FiscalDocumentResource::class;

    protected static ?string $title = 'Documento fiscal';

    protected function getHeaderActions(): array
    {
        return FiscalDocumentActions::all();
    }
}
