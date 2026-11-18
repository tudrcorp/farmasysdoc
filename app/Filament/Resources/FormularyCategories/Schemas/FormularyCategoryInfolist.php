<?php

namespace App\Filament\Resources\FormularyCategories\Schemas;

use App\Models\FormularyCategory;
use App\Models\FormularyCategoryItem;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class FormularyCategoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Categoría')
                    ->icon(Heroicon::RectangleStack)
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 4])
                            ->schema([
                                TextEntry::make('code')->label('Código')->badge()->color('gray'),
                                TextEntry::make('name')->label('Nombre')->weight('medium')->columnSpan(['md' => 2]),
                                IconEntry::make('is_active')->label('Activa')->boolean(),
                                TextEntry::make('description')->label('Descripción')->placeholder('—')->columnSpanFull(),
                            ]),
                    ])
                    ->columnSpanFull(),
                Section::make('Presentaciones')
                    ->icon(Heroicon::Beaker)
                    ->description(fn (FormularyCategory $record): string => $record->items()->count().' presentación(es) · '.$record->activeIngredients()->distinct()->count('active_ingredients.id').' principio(s) activo(s)')
                    ->schema([
                        TextEntry::make('presentations')
                            ->hiddenLabel()
                            ->state(fn (FormularyCategory $record): array => $record->items()->with('activeIngredient:id,name')->get()
                                ->map(fn (FormularyCategoryItem $item): string => $item->label())
                                ->all())
                            ->listWithLineBreaks()
                            ->bulleted()
                            ->placeholder('Sin presentaciones asociadas'),
                    ])
                    ->columnSpanFull(),
                Section::make('Catálogos que la usan')
                    ->icon(Heroicon::BookOpen)
                    ->schema([
                        TextEntry::make('formularies.name')
                            ->hiddenLabel()
                            ->badge()
                            ->color('primary')
                            ->placeholder('Ningún catálogo la usa todavía'),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
