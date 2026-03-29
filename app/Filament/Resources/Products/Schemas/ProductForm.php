<?php

namespace App\Filament\Resources\Products\Schemas;

use App\Enums\ProductType;
use App\Models\ActiveIngredient;
use App\Models\PresentationType;
use App\Models\Supplier;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
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
                                Select::make('product_type')
                                    ->label('Tipo de producto')
                                    ->options(ProductType::options())
                                    ->native(false)
                                    ->searchable()
                                    ->required()
                                    ->prefixIcon(Heroicon::Squares2x2),
                            ]),
                        TextInput::make('name')
                            ->label('Nombre comercial')
                            ->placeholder('Como aparece en factura y etiqueta')
                            ->helperText('Nombre principal del producto en catálogo.')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (?string $state, Set $set): void {
                                if (blank($state)) {
                                    $set('slug', null);

                                    return;
                                }

                                $set('slug', Str::slug(Str::lower($state)));
                            })
                            ->prefixIcon(Heroicon::ShoppingBag)
                            ->columnSpanFull(),
                        TextInput::make('slug')
                            ->label('Slug (URL)')
                            ->placeholder('se-genera-o-se-edita-manualmente')
                            ->helperText('Opcional. Identificador amigable para enlaces o integraciones.')
                            ->disabled()
                            ->dehydrated(true)
                            ->maxLength(255)
                            ->prefixIcon(Heroicon::Link)
                            ->dehydrateStateUsing(fn (?string $state): ?string => $state === '' || $state === null ? null : $state),
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

                Section::make('Unidad de venta y precios')
                    ->description('Cómo se vende el artículo y valores de lista e impuesto.')
                    ->icon(Heroicon::CurrencyDollar)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 3,
                        ])
                            ->schema([
                                TextInput::make('sale_price')
                                    ->label('Precio de venta')
                                    ->helperText('Precio al público o lista base.')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->prefix('$'),
                                TextInput::make('cost_price')
                                    ->label('Costo')
                                    ->helperText('Costo de adquisición o valoración interna.')
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.01)
                                    ->prefix('$'),
                                TextInput::make('tax_rate')
                                    ->label('Tasa de impuesto')
                                    ->helperText('Porcentaje (ej. IVA). Use 0 si no aplica.')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(100)
                                    ->step(0.01)
                                    ->default(0.0)
                                    ->suffix('%')
                                    ->prefixIcon(Heroicon::Calculator),
                            ]),
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
}
