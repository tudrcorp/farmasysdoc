<?php

namespace App\Filament\Resources\ProductCategories\Schemas;

use App\Models\User;
use App\Services\Products\CatalogImageOptimizer;
use App\Support\Products\CatalogImageFileUpload;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;

class ProductCategoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Datos generales')
                    ->description('Nombre, URL amigable y descripción para catálogo y panel.')
                    ->icon(Heroicon::Tag)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre de la categoría')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                if (filled($state)) {
                                    $set('slug', Str::slug($state));
                                }
                            }),
                        TextInput::make('slug')
                            ->label('Slug (URL)')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->alphaDash()
                            ->helperText('Identificador único en URL. Se sugiere desde el nombre; puede ajustarlo si hace falta.'),
                        Textarea::make('description')
                            ->label('Descripción')
                            ->rows(3)
                            ->maxLength(255)
                            ->columnSpanFull()
                            ->placeholder('Opcional. Texto breve para ayudas y fichas.'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),

                Section::make('Imagen')
                    ->description('Miniatura opcional para listados y detalle de la categoría.')
                    ->icon(Heroicon::Photo)
                    ->schema([
                        CatalogImageFileUpload::make('image', CatalogImageOptimizer::CATEGORIES)
                            ->label('Imagen')
                            ->helperText('JPG, PNG o JPEG se convierten a WebP (máx. 800 px y 4 MB).'),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Comercial')
                    ->description('Visibilidad en catálogo y margen por defecto para nuevas sucursales. Cada sucursal puede ajustar su propio margen por categoría.')
                    ->icon(Heroicon::ChartBar)
                    ->schema([
                        Toggle::make('is_active')
                            ->label('Categoría activa')
                            ->visible(function (): bool {
                                $authUser = request()->user();

                                if (! $authUser instanceof User) {
                                    return false;
                                }

                                return ! $authUser->isManager();
                            })
                            ->helperText('Si está desactivada, no se muestra en listas de selección y no afecta cálculos de precios.')
                            ->default(true)
                            ->inline(false),
                        Toggle::make('is_medication')
                            ->label('Categoría de medicamentos')
                            ->helperText('Si está activo, la API de aliados y el snapshot de inventario tratan el producto como medicamento (p. ej. principio activo en inventario).')
                            ->default(false)
                            ->inline(false),
                        TextInput::make('profit_percentage')
                            ->label('Margen / porcentaje (%)')
                            ->numeric()
                            ->suffix('%')
                            ->minValue(0)
                            ->default(0)
                            ->helperText('Valor por defecto al crear sucursales o categorías. Las sucursales pueden personalizarlo en Configuración → Sucursales → Márgenes por categoría.')
                            ->placeholder('0'),
                    ])
                    ->columns(2)
                    ->columnSpanFull(),
            ]);
    }
}
