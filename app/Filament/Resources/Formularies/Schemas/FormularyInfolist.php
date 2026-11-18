<?php

namespace App\Filament\Resources\Formularies\Schemas;

use App\Models\Formulary;
use App\Models\FormularyCategory;
use App\Models\FormularyCategoryItem;
use App\Support\Plans\FormularyCoverage;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class FormularyInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Catálogo')
                    ->icon(Heroicon::BookOpen)
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 4])
                            ->schema([
                                TextEntry::make('code')->label('Código')->badge()->color('gray'),
                                TextEntry::make('name')->label('Nombre')->weight('medium')->columnSpan(['md' => 2]),
                                IconEntry::make('is_active')->label('Activo')->boolean(),
                                TextEntry::make('requirements')
                                    ->label('Requisitos')
                                    ->state(fn (Formulary $record): ?string => $record->requirementsLabel())
                                    ->badge()
                                    ->color('warning')
                                    ->placeholder('Sin requisitos'),
                                TextEntry::make('description')->label('Descripción')->placeholder('—')->columnSpanFull(),
                                TextEntry::make('coverage')
                                    ->label('Cobertura')
                                    ->state(fn (Formulary $record): string => FormularyCoverage::forCategories($record->categories()->pluck('formulary_categories.id')->all()))
                                    ->icon(Heroicon::Beaker)
                                    ->color('info')
                                    ->columnSpan(['md' => 3]),
                                TextEntry::make('plans.name')
                                    ->label('Planes que lo usan')
                                    ->badge()
                                    ->color('primary')
                                    ->placeholder('Ningún plan lo usa todavía')
                                    ->columnSpan(['md' => 2]),
                                TextEntry::make('creator.name')->label('Creado por')->placeholder('—'),
                                TextEntry::make('created_at')->label('Creado')->dateTime('d/m/Y h:i a'),
                                TextEntry::make('updater.name')->label('Actualizado por')->placeholder('—'),
                                TextEntry::make('updated_at')->label('Actualizado')->dateTime('d/m/Y h:i a'),
                            ]),
                    ])
                    ->columnSpanFull(),
                Section::make('Categorías y principios activos')
                    ->icon(Heroicon::RectangleStack)
                    ->schema([
                        RepeatableEntry::make('categories')
                            ->hiddenLabel()
                            ->schema([
                                TextEntry::make('name')
                                    ->hiddenLabel()
                                    ->weight('medium')
                                    ->icon(Heroicon::RectangleStack),
                                TextEntry::make('presentations')
                                    ->hiddenLabel()
                                    ->state(fn (FormularyCategory $record): array => $record->items()->with('activeIngredient:id,name')->get()
                                        ->map(fn (FormularyCategoryItem $item): string => $item->label())
                                        ->all())
                                    ->listWithLineBreaks()
                                    ->bulleted()
                                    ->placeholder('Sin presentaciones'),
                            ])
                            ->placeholder('Sin categorías'),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
