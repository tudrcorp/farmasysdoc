<?php

namespace App\Filament\Resources\PurchaseLedgers\Pages;

use App\Filament\Resources\PurchaseLedgers\PurchaseLedgerResource;
use App\Models\PurchaseLedger;
use Filament\Resources\Pages\ViewRecord;
use Illuminate\Contracts\Support\Htmlable;

class ViewPurchaseLedger extends ViewRecord
{
    protected static string $resource = PurchaseLedgerResource::class;

    public function getTitle(): string|Htmlable
    {
        $record = $this->getRecord();
        if ($record instanceof PurchaseLedger) {
            return 'Op. '.$record->operation_number.' · '.($record->document_type?->label() ?? 'Documento');
        }

        return 'Detalle del Libro de Compras';
    }
}
