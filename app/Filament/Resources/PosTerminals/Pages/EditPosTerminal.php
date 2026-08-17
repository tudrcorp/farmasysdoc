<?php

namespace App\Filament\Resources\PosTerminals\Pages;

use App\Filament\Resources\PosTerminals\PosTerminalResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;

class EditPosTerminal extends EditRecord
{
    protected static string $resource = PosTerminalResource::class;

    protected static ?string $title = 'Editar punto de venta';

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make()
                ->label('Ver')
                ->icon(Heroicon::Eye),
            DeleteAction::make()
                ->label('Eliminar')
                ->icon(Heroicon::Trash),
        ];
    }
}
