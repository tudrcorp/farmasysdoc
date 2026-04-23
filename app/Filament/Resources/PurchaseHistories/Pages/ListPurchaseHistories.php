<?php

namespace App\Filament\Resources\PurchaseHistories\Pages;

use App\Filament\Resources\PurchaseHistories\PurchaseHistoryResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;

class ListPurchaseHistories extends ListRecords
{
    protected static string $resource = PurchaseHistoryResource::class;

    protected static ?string $title = 'Histórico de compras';

    public function getSubheading(): string|Htmlable|null
    {
        return 'Registro cronológico de compras al contado y de cada pago registrado contra cuentas por pagar (método, forma, fecha y montos). Sirve como fuente de verdad para auditoría y futuros procesos.';
    }
}
