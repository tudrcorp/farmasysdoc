<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Models\ActiveIngredient;
use App\Models\PresentationType;
use App\Models\Product;
use App\Models\Supplier;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

class ProductForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificación comercial')
                    ->description('Códigos, nombre y clasificación del artículo en catálogo y punto de venta.')
                    ->icon(Heroicon::Cube)
                    ->schema([
                        Select::make('supplier_id')
                            ->label('Proveedor principal')
                            ->relationship(
                                name: 'supplier',
                                titleAttribute: 'legal_name',
                                modifyQueryUsing: fn ($query) => $query->where('is_active', true)->orderBy('legal_name'),
                            )
                            ->getOptionLabelFromRecordUsing(
                                fn (Supplier $record): string => $record->trade_name ?: $record->legal_name,
                            )
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->placeholder('Sin proveedor asignado')
                            ->helperText('Opcional. Facilita compras y trazabilidad con el laboratorio o distribuidor.')
                            ->prefixIcon(Heroicon::Truck),
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 3,
                        ])
                            ->schema([
                                TextInput::make('barcode')
                                    ->label('Código de barras / EAN')
                                    ->placeholder('Opcional')
                                    ->helperText('Debe ser único si se registra. Si lo dejas vacío, se genera automáticamente como 00 + ID del producto.')
                                    ->maxLength(255)
                                    ->unique(ignoreRecord: true)
                                    ->prefixIcon(Heroicon::QrCode)
                                    ->dehydrateStateUsing(fn (?string $state): ?string => $state === '' || $state === null ? null : $state),
                            ]),
                        TextInput::make('name')
                            ->label('Nombre comercial')
                            ->placeholder('Como aparece en factura y etiqueta')
                            ->helperText('Nombre principal del producto en catálogo.')
                            ->required()
                            ->maxLength(255)
                            ->prefixIcon(Heroicon::ShoppingBag)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                if (filled($state)) {
                                    $set('slug', Str::slug($state));
                                }
                            })
                            ->columnSpanFull(),
                        TextInput::make('slug')
                            ->label('Slug (URL)')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->alphaDash()
                            ->helperText('Identificador único para URLs y búsqueda. Se sugiere desde el nombre; puede ajustarlo.')
                            ->prefixIcon(Heroicon::Link)
                            ->columnSpanFull(),
                        Textarea::make('description')
                            ->label('Descripción')
                            ->placeholder('Características, uso, advertencias breves para el mostrador…')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Imágenes del producto')
                    ->description('Fotografía para catálogo, fichas y listados. Estilo vidrio iOS en panel.')
                    ->icon(Heroicon::Photo)
                    ->schema([
                        FileUpload::make('image')
                            ->label('Fotografía principal')
                            ->helperText('Opcional. JPG, PNG o WebP (máx. 4 MB). Visible en tabla y detalle.')
                            ->image()
                            ->disk('public')
                            ->directory('products')
                            ->visibility('public')
                            ->maxSize(4096)
                            ->imageEditor()
                            ->panelLayout('integrated')
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull()
                    ->extraAttributes([
                        'class' => 'fi-farmaadmin-ios-product-images-section',
                    ]),

                Section::make('Precios e impuestos')
                    ->description('El precio de venta se calcula: costo + (costo × % de ganancia de la categoría). Misma política en todas las sucursales.')
                    ->icon(Heroicon::CurrencyDollar)
                    ->schema([
                        Select::make('product_category_id')
                            ->label('Categoría del producto')
                            ->relationship(
                                name: 'productCategory',
                                titleAttribute: 'name',
                                modifyQueryUsing: fn (Builder $query): Builder => $query->where('is_active', true)->orderBy('name'),
                            )
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required()
                            ->live()
                            ->afterStateUpdated(function (Get $get, Set $set): void {
                                self::syncComputedSalePriceToForm($get, $set);
                            })
                            ->helperText('El margen % definido en la categoría se aplica sobre el costo para obtener el precio de venta (lista).')
                            ->prefixIcon(Heroicon::Swatch)
                            ->columnSpanFull(),
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 4,
                        ])
                            ->schema([
                                TextInput::make('cost_price')
                                    ->label('Costo de compra')
                                    ->helperText('Base para calcular el precio de venta junto con el margen de la categoría.')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->prefix('$')
                                    ->default(0)
                                    ->live(onBlur: true)
                                    ->afterStateUpdated(function (Get $get, Set $set): void {
                                        self::syncComputedSalePriceToForm($get, $set);
                                    })
                                    ->prefixIcon(Heroicon::ReceiptPercent),
                                TextInput::make('sale_price')
                                    ->label('Precio de venta (lista)')
                                    ->helperText('Calculado automáticamente. Antes del descuento % comercial del producto.')
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->numeric()
                                    ->prefix('$')
                                    ->default(0)
                                    ->prefixIcon(Heroicon::Banknotes),
                                TextInput::make('discount_percent')
                                    ->label('Descuento (%)')
                                    ->helperText('Porcentaje sobre el precio lista en catálogo.')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(0.01)
                                    ->default(0)
                                    ->suffix('%')
                                    ->prefixIcon(Heroicon::Tag),
                            ]),
                        Toggle::make('applies_vat')
                            ->label('Grava IVA en pedidos')
                            ->helperText(fn (): string => 'Si está activo, en pedidos se calcula el IVA sobre la base de cada línea (precio neto tras el descuento %). Tasa global: '.config('orders.default_vat_rate_percent').'%.')
                            ->default(false)
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Información farmacéutica y registro')
                    ->description('Datos para medicamentos: principio activo, registro sanitario y requisitos legales.')
                    ->icon(Heroicon::Beaker)
                    ->schema([
                        Select::make('active_ingredient')
                            ->label('Principio(s) activo(s)')
                            ->placeholder('Seleccione uno o más principios activos')
                            ->options(fn (): array => ActiveIngredient::query()
                                ->orderBy('name')
                                ->pluck('name', 'name')
                                ->all())
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->columnSpanFull(),
                        Grid::make([
                            'default' => 1,
                            'sm' => 3,
                            'lg' => 3,
                        ])
                            ->schema([
                                TextInput::make('brand')
                                    ->label('Marca / laboratorio')
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::BuildingStorefront),
                                TextInput::make('concentration')
                                    ->label('Concentración')
                                    ->placeholder('Ej. 500 mg, 10 mg/ml')
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::Beaker),
                                Select::make('presentation_type')
                                    ->label('Forma farmacéutica')
                                    ->placeholder('Seleccione la forma farmacéutica')
                                    ->options(fn (): array => PresentationType::query()
                                        ->orderBy('name')
                                        ->pluck('name', 'name')
                                        ->all())
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->prefixIcon(Heroicon::Cube),
                            ]),
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                Toggle::make('requires_prescription')
                                    ->label('Requiere fórmula médica')
                                    ->helperText('Indica si la venta exige receta u orden médica.')
                                    ->inline(false)
                                    ->default(false),
                                Toggle::make('is_controlled_substance')
                                    ->label('Sustancia controlada / psicotrópico')
                                    ->helperText('Activa controles adicionales según normativa local.')
                                    ->inline(false)
                                    ->default(false),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Composición, alérgenos y nutrición')
                    ->description('Relevante para alimentos, suplementos y algunos productos de cuidado personal.')
                    ->icon(Heroicon::Cake)
                    ->collapsed()
                    ->schema([
                        Textarea::make('ingredients')
                            ->label('Ingredientes')
                            ->rows(3)
                            ->columnSpanFull(),
                        Textarea::make('allergens')
                            ->label('Alérgenos declarados')
                            ->placeholder('Gluten, leche, soya…')
                            ->rows(2)
                            ->columnSpanFull(),
                        Textarea::make('nutritional_information')
                            ->label('Información nutricional')
                            ->placeholder('Tabla o texto según empaque')
                            ->rows(3)
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Fabricación y dispositivo médico')
                    ->description('Datos de fabricante, modelo, garantía y clase reglamentaria.')
                    ->icon(Heroicon::WrenchScrewdriver)
                    ->collapsed()
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextInput::make('manufacturer')
                                    ->label('Fabricante')
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::BuildingOffice2),
                                TextInput::make('model')
                                    ->label('Modelo / referencia')
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::CpuChip),
                            ]),
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextInput::make('warranty_months')
                                    ->label('Garantía (meses)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(65535)
                                    ->prefixIcon(Heroicon::CalendarDays),
                                TextInput::make('medical_device_class')
                                    ->label('Clase de dispositivo médico')
                                    ->placeholder('I, IIa, IIb, III')
                                    ->maxLength(255)
                                    ->prefixIcon(Heroicon::ShieldCheck),
                            ]),
                        Toggle::make('requires_calibration')
                            ->label('Requiere calibración periódica')
                            ->helperText('Para equipos que necesitan mantenimiento metrológico.')
                            ->inline(false)
                            ->default(false),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Almacenamiento y visibilidad')
                    ->description('Condiciones de conservación y si el producto está disponible en catálogo y ventas.')
                    ->icon(Heroicon::ArchiveBox)
                    ->schema([
                        Textarea::make('storage_conditions')
                            ->label('Condiciones de almacenamiento')
                            ->placeholder('Temperatura, luz, humedad, cadena de frío…')
                            ->rows(3)
                            ->columnSpanFull(),
                        Toggle::make('is_active')
                            ->label('Producto activo')
                            ->helperText('Si está inactivo, puede ocultarse en ventas o catálogos según la configuración del sistema.')
                            ->inline(false)
                            ->default(true),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }

    private static function syncComputedSalePriceToForm(Get $get, Set $set): void
    {
        if (! filled($get('product_category_id'))) {
            $set('sale_price', 0);

            return;
        }

        $cost = $get('cost_price');
        $costAmount = ($cost === null || $cost === '') ? 0.0 : (float) $cost;

        $set(
            'sale_price',
            Product::salePriceFromCostAndCategoryProfit(
                $costAmount,
                (int) $get('product_category_id'),
            ),
        );
    }
}
