<?php

namespace App\Filament\Resources\FiscalZReports\Pages;

use App\Filament\Resources\FiscalZReports\FiscalZReportResource;
use App\Models\FiscalZReport;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewFiscalZReport extends ViewRecord
{
    protected static string $resource = FiscalZReportResource::class;

    public function getTitle(): string|Htmlable
    {
        $record = $this->getRecord();

        return $record instanceof FiscalZReport
            ? 'Reporte Z '.str_pad((string) $record->z_number, 4, '0', STR_PAD_LEFT).' · '.($record->fiscalPrinter?->name ?? '')
            : parent::getTitle();
    }
}
