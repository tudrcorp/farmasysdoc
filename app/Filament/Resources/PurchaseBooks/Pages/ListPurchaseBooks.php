<?php

namespace App\Filament\Resources\PurchaseBooks\Pages;

use App\Filament\Resources\PurchaseBooks\Actions\DownloadPurchaseBookSeniatRetentionTxtAction;
use App\Filament\Resources\PurchaseBooks\PurchaseBookResource;
use App\Filament\Resources\PurchaseBooks\Widgets\StatsPurchaseBookOverview;
use Filament\Actions\Action;
use Filament\Pages\Concerns\ExposesTableToWidgets;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Support\HtmlString;

class ListPurchaseBooks extends ListRecords
{
    use ExposesTableToWidgets;

    protected static string $resource = PurchaseBookResource::class;

    protected static ?string $title = 'Retenciones';

    public function getHeading(): string|Htmlable
    {
        return new HtmlString(
            '<span class="inline-flex flex-wrap items-center gap-2">'
            .'<span>Retenciones</span>'
            .'<span class="inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-semibold tracking-wide '
            .'bg-primary-500/15 text-primary-700 ring-1 ring-inset ring-primary-500/30 '
            .'dark:bg-primary-400/15 dark:text-primary-300 dark:ring-primary-400/35">'
            .'SENIAT · Retención IVA'
            .'</span>'
            .'</span>'
        );
    }

    public function getSubheading(): string|Htmlable|null
    {
        return new HtmlString(
            'Registros agrupados por <strong>proveedor</strong> y <strong>fecha de factura</strong>. '
            .'Expanda un grupo para revisar líneas, use <strong>Imprimir PDF</strong> para el comprobante o <strong>TXT SENIAT</strong> para el archivo del portal.'
        );
    }

    /**
     * @return array<class-string>
     */
    protected function getHeaderWidgets(): array
    {
        return [
            StatsPurchaseBookOverview::class,
        ];
    }

    public function getHeaderWidgetsColumns(): int|array
    {
        return 1;
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            DownloadPurchaseBookSeniatRetentionTxtAction::make(),
        ];
    }
}
