<?php

namespace App\Filament\Resources\FiscalPrinters\Pages;

use App\Filament\Resources\FiscalPrinters\FiscalPrinterResource;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditFiscalPrinter extends EditRecord
{
    protected static string $resource = FiscalPrinterResource::class;

    protected static ?string $title = 'Editar máquina fiscal';

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()
                ->label('Ver')
                ->icon(Heroicon::Eye),
        ];
    }
}
