<?php

namespace App\Filament\Resources\InventoryStockFailures\Pages;

use App\Filament\Resources\InventoryStockFailures\InventoryStockFailureResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

class ViewInventoryStockFailure extends ViewRecord
{
    protected static string $resource = InventoryStockFailureResource::class;

    public function getTitle(): string|Htmlable
    {
        return 'Falla #'.$this->getRecord()->getKey();
    }

    public function getSubheading(): string|Htmlable|null
    {
        $record = $this->getRecord();

        return (string) ($record->product_name ?? 'Producto sin nombre');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('back')
                ->label('Volver al listado')
                ->icon(Heroicon::ArrowLeft)
                ->color('gray')
                ->extraAttributes([
                    'class' => 'farmadoc-ios-action',
                ])
                ->url(InventoryStockFailureResource::getUrl('index', isAbsolute: false)),
        ];
    }
}
