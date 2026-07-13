<?php

namespace App\Filament\Resources\Branches\Pages;

use App\Filament\Resources\Branches\BranchResource;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewBranch extends ViewRecord
{
    protected static string $resource = BranchResource::class;

    protected static ?string $title = 'Detalle de la Sucursal';

    protected function getHeaderActions(): array
    {
        return [
            Action::make('profitMargins')
                ->label('Márgenes por categoría')
                ->icon(Heroicon::ChartBarSquare)
                ->color('warning')
                ->url(fn (): string => BranchResource::getUrl('profit-margins', ['record' => $this->getRecord()])),
            EditAction::make()
                ->label('Editar Sucursal')
                ->icon(Heroicon::Pencil)
                ->color('primary')
                ->size('sm'),
        ];
    }
}
