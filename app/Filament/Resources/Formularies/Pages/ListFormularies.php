<?php

namespace App\Filament\Resources\Formularies\Pages;

use App\Filament\Resources\Formularies\FormularyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListFormularies extends ListRecords
{
    protected static string $resource = FormularyResource::class;

    protected static ?string $title = 'Catálogos';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nuevo catálogo')
                ->icon(Heroicon::Plus),
        ];
    }
}
