<?php

namespace App\Filament\Resources\Formularies\Schemas;

use App\Models\Formulary;
use App\Models\FormularyCategory;
use App\Support\Plans\FormularyCoverage;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class FormularyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Catálogo')
                    ->description('Un catálogo puede estar asociado a uno o varios planes.')
                    ->icon(Heroicon::BookOpen)
                    ->schema([
                        Grid::make(['default' => 1, 'md' => 3])
                            ->schema([
                                TextInput::make('code')
                                    ->label('Código')
                                    ->default(fn (): string => Formulary::nextCode())
                                    ->required()
                                    ->maxLength(30)
                                    ->unique(ignoreRecord: true)
                                    ->validationMessages(['unique' => 'Ya existe un catálogo con ese código.'])
                                    ->prefixIcon(Heroicon::Hashtag),
                                TextInput::make('name')
                                    ->label('Nombre')
                                    ->placeholder('Ej.: Catálogo crónicos, Catálogo básico ambulatorio')
                                    ->required()
                                    ->maxLength(255)
                                    ->columnSpan(['md' => 2]),
                                Textarea::make('description')
                                    ->label('Descripción')
                                    ->rows(2)
                                    ->columnSpanFull(),
                                Toggle::make('is_active')
                                    ->label('Activo')
                                    ->helperText('Los catálogos inactivos no se ofrecen al armar planes.')
                                    ->default(true)
                                    ->inline(false),
                                Toggle::make('requires_prescription')
                                    ->label('Requiere récipe')
                                    ->default(true)
                                    ->inline(false),
                                Toggle::make('requires_authorization')
                                    ->label('Requiere autorización')
                                    ->helperText('Autorización previa para dispensar.')
                                    ->default(true)
                                    ->inline(false),
                            ]),
                    ])
                    ->columnSpanFull(),

                Section::make('Categorías')
                    ->description('Elija las categorías precargadas en el maestro. Cada una trae sus presentaciones de principios activos.')
                    ->icon(Heroicon::RectangleStack)
                    ->schema([
                        Select::make('categories')
                            ->label('Categorías')
                            ->relationship(
                                'categories',
                                'name',
                                fn (Builder $query, ?Formulary $record): Builder => $query
                                    ->where(fn (Builder $inner): Builder => $inner
                                        ->where('is_active', true)
                                        ->when($record !== null, fn (Builder $q): Builder => $q->orWhereHas('formularies', fn (Builder $f): Builder => $f->whereKey($record->getKey()))))
                                    ->withCount('items')
                                    ->orderBy('name'),
                            )
                            ->getOptionLabelFromRecordUsing(fn (FormularyCategory $record): string => "{$record->name} ({$record->items_count} pres.)")
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->live()
                            ->required()
                            ->validationMessages(['required' => 'Seleccione al menos una categoría.']),
                        TextEntry::make('coverage_preview')
                            ->label('Cobertura')
                            ->state(fn (Get $get): string => FormularyCoverage::forCategories((array) ($get('categories') ?? [])))
                            ->icon(Heroicon::Beaker)
                            ->color('info'),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
