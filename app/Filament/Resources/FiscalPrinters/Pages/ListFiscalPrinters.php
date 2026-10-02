<?php

namespace App\Filament\Resources\FiscalPrinters\Pages;

use App\Filament\Resources\FiscalPrinters\Actions\FiscalAgentPackageActions;
use App\Filament\Resources\FiscalPrinters\FiscalPrinterResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListFiscalPrinters extends ListRecords
{
    protected static string $resource = FiscalPrinterResource::class;

    protected static ?string $title = 'Máquinas fiscales';

    protected function getHeaderActions(): array
    {
        return [
            FiscalAgentPackageActions::download(),
            FiscalAgentPackageActions::upload(),
            CreateAction::make()
                ->label('Nueva máquina fiscal')
                ->icon(Heroicon::Plus)
                ->color('primary')
                ->extraAttributes([
                    'class' => 'farmadoc-ios-action farmadoc-ios-action--primary',
                ]),
        ];
    }
}
