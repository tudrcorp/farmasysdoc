<?php

namespace App\Filament\Resources\FormularyCategories\Pages;

use App\Filament\Resources\FormularyCategories\FormularyCategoryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;

class ListFormularyCategories extends ListRecords
{
    protected static string $resource = FormularyCategoryResource::class;

    protected static ?string $title = 'Categorías de catálogo';

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('Nueva categoría')
                ->icon(Heroicon::Plus),
        ];
    }
}
