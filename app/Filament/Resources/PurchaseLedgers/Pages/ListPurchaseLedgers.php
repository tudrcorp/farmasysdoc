<?php

namespace App\Filament\Resources\PurchaseLedgers\Pages;

use App\Filament\Resources\PurchaseLedgers\PurchaseLedgerResource;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ListPurchaseLedgers extends ListRecords
{
    protected static string $resource = PurchaseLedgerResource::class;

    protected static ?string $title = 'Libro de Compras';

    public function getHeading(): string|Htmlable
    {
        return new HtmlString(
            '<span class="inline-flex flex-wrap items-center gap-2">'
            .'<span>Libro de Compras</span>'
            .'<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold tracking-wide '
            .'bg-primary-500/15 text-primary-700 ring-1 ring-inset ring-primary-500/30 '
            .'dark:bg-primary-400/15 dark:text-primary-300 dark:ring-primary-400/35">'
            .'SENIAT'
            .'</span>'
            .'</span>'
        );
    }

    public function getSubheading(): string|Htmlable|null
    {
        return new HtmlString(
            'Registro automático al guardar compras: factura y, si aplica, comprobante de retención con montos SENIAT.'
        );
    }
}
