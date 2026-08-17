<?php

namespace App\Filament\Resources\PosTerminals\Pages;

use App\Filament\Resources\PosTerminals\PosTerminalResource;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewPosTerminal extends ViewRecord
{
    protected static string $resource = PosTerminalResource::class;

    protected static ?string $title = 'Punto de venta';

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make()
                ->label('Editar')
                ->icon(Heroicon::PencilSquare),
        ];
    }
}
