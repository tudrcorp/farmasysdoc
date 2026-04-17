<?php

namespace App\Filament\Resources\Inventories\Schemas;

use App\Models\ActiveIngredient;
use App\Models\Inventory;
use App\Models\Product;
use App\Support\Filament\BranchAuthScope;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
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

class InventoryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Sucursal y producto')
                    ->description('Cada combinación sucursal + producto es única en el inventario.')
                    ->icon(Heroicon::BuildingStorefront)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                Select::make('branch_id')
                                    ->label('Sucursal')
                                    ->relationship(
                                        name: 'branch',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: function (Builder $query): Builder {
                                            $query->where('is_active', true)->orderBy('name');

                                            return BranchAuthScope::applyToBranchFormSelect($query);
                                        },
                                    )
                                    ->default(fn (): ?int => BranchAuthScope::suggestedBranchIdForOperationalForm())
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->required()
                                    ->live()
                                    ->afterStateUpdated(fn (Set $set) => $set('product_id', null))
                                    ->helperText('Solo sucursales activas.')
                                    ->prefixIcon(Heroicon::BuildingOffice2),
                                Select::make('product_id')
                                    ->label('Producto')
                                    ->relationship(
                                        name: 'product',
                                        titleAttribute: 'name',
                                        modifyQueryUsing: fn (Builder $query) => $query->where('is_active', true)->orderBy('name'),
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->required()
                                    ->live(onBlur: false)
                                    ->afterStateUpdated(function (?string $state, Set $set): void {
                                        if (blank($state)) {
                                            $set('product_category_id', null);
                                            $set('active_ingredient', []);
                                            $set('concentration', null);
                                            $set('presentation_type', null);

                                            return;
                                        }

                                        $product = Product::query()->find($state);

                                        if ($product === null) {
                                            return;
                                        }

                                        foreach (Inventory::pharmacySnapshotFromProduct($product) as $key => $value) {
                                            $set($key, $value);
                                        }
                                    })
                                    ->disabled(fn (Get $get): bool => blank($get('branch_id')))
                                    ->helperText('Seleccione primero la sucursal. No puede repetir el mismo producto en la misma sucursal.')
                                    ->prefixIcon(Heroicon::Cube)
                                    ->scopedUnique(
                                        model: Inventory::class,
                                        column: 'product_id',
                                        ignoreRecord: true,
                                        modifyQueryUsing: function (Builder $query, Get $get): Builder {
                                            $branchId = $get('branch_id');

                                            if (blank($branchId)) {
                                                return $query->whereRaw('1 = 0');
                                            }

                                            return $query->where('branch_id', $branchId);
                                        },
                                    ),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Características del producto (catálogo)')
                    ->description('Se completan al elegir el producto; se guardan como referencia al crear o al cambiar de producto.')
                    ->icon(Heroicon::Beaker)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                Select::make('product_category_id')
                                    ->label('Categoría (catálogo)')
                                    ->relationship(
                                        name: 'productCategory',
                                        titleAttribute: 'name',
                                    )
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->native(false),
                                TextInput::make('presentation_type')
                                    ->label('Forma farmacéutica / presentación')
                                    ->disabled()
                                    ->dehydrated(true),
                                TextInput::make('concentration')
                                    ->label('Concentración')
                                    ->disabled()
                                    ->dehydrated(true),
                            ]),
                        Select::make('active_ingredient')
                            ->label('Principio(s) activo(s)')
                            ->options(fn (): array => ActiveIngredient::query()->orderBy('name')->pluck('name', 'name')->all())
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->disabled()
                            ->dehydrated(true)
                            ->columnSpanFull(),
                    ])
                    ->visible(fn (Get $get): bool => filled($get('product_id')))
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Precios del producto')
                    ->description('Snapshot financiero en inventario: se recalcula con costo de compra, IVA del sistema y % de ganancia de categoría.')
                    ->icon(Heroicon::CurrencyDollar)
                    ->visible(fn (Get $get): bool => filled($get('product_id')))
                    ->schema([
                        Placeholder::make('pricing_from_product_hint')
                            ->label('')
                            ->content('Estos campos se actualizan al recibir compras para esta sucursal.'),
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 3,
                        ])
                            ->schema([
                                TextInput::make('cost_price')
                                    ->label('Costo')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->prefix('$'),
                                TextInput::make('vat_cost_amount')
                                    ->label('Valor del IVA (costo)')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->prefix('$'),
                                TextInput::make('cost_plus_vat')
                                    ->label('Costo + IVA')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->prefix('$'),
                                TextInput::make('final_price_without_vat')
                                    ->label('Precio final sin IVA')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->prefix('$'),
                                TextInput::make('vat_final_price_amount')
                                    ->label('Valor IVA precio final')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->prefix('$'),
                                TextInput::make('final_price_with_vat')
                                    ->label('Precio final con IVA')
                                    ->numeric()
                                    ->disabled()
                                    ->dehydrated(true)
                                    ->prefix('$'),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Existencias')
                    ->description('Cantidades físicas y reservas que afectan la disponibilidad para venta.')
                    ->icon(Heroicon::ArchiveBox)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextInput::make('quantity')
                                    ->label('Existencias actuales')
                                    ->helperText('Stock físico o lógico en esta sucursal.')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.001)
                                    ->default(0.0)
                                    ->prefixIcon(Heroicon::SquaresPlus),
                                TextInput::make('reserved_quantity')
                                    ->label('Cantidad reservada')
                                    ->helperText('Apartada para pedidos, órdenes o trámites.')
                                    ->required()
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.001)
                                    ->default(0.0)
                                    ->prefixIcon(Heroicon::LockClosed),
                            ]),
                        Toggle::make('allow_negative_stock')
                            ->label('Permitir saldo negativo')
                            ->helperText('Solo en casos excepcionales; la disponible para venta puede mostrarse en cero si está desactivado.')
                            ->inline(false)
                            ->default(false),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Políticas y ubicación')
                    ->description('Umbrales de reposición y dónde se almacena el producto en la sucursal.')
                    ->icon(Heroicon::MapPin)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 3,
                        ])
                            ->schema([
                                TextInput::make('reorder_point')
                                    ->label('Punto de reorden')
                                    ->helperText('Alerta cuando el stock alcanza o baja de este nivel.')
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.001)
                                    ->prefixIcon(Heroicon::BellAlert),
                                TextInput::make('minimum_stock')
                                    ->label('Stock mínimo deseado')
                                    ->helperText('Política interna de inventario.')
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.001)
                                    ->prefixIcon(Heroicon::ArrowTrendingDown),
                                TextInput::make('maximum_stock')
                                    ->label('Stock máximo sugerido')
                                    ->helperText('Tope orientativo de almacenamiento.')
                                    ->numeric()
                                    ->minValue(0)
                                    ->step(0.001)
                                    ->prefixIcon(Heroicon::ArrowTrendingUp),
                            ]),
                        TextInput::make('storage_location')
                            ->label('Ubicación física')
                            ->placeholder('Pasillo, nevera, estante, código de bin…')
                            ->maxLength(255)
                            ->prefixIcon(Heroicon::QrCode)
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Auditoría de movimientos')
                    ->description('Fechas de referencia; suelen actualizarse con movimientos y conteos físicos.')
                    ->icon(Heroicon::Clock)
                    ->collapsed()
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                DateTimePicker::make('last_movement_at')
                                    ->label('Último movimiento')
                                    ->helperText('Entrada, salida, ajuste u otro movimiento registrado.')
                                    ->native(false)
                                    ->seconds(false),
                                DateTimePicker::make('last_stock_take_at')
                                    ->label('Último conteo / auditoría')
                                    ->helperText('Última verificación física o arqueo.')
                                    ->native(false)
                                    ->seconds(false),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Notas internas')
                    ->description('Observaciones solo para el equipo (lotes, incidencias, acuerdos locales).')
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->collapsed()
                    ->schema([
                        Textarea::make('notes')
                            ->label('Observaciones')
                            ->placeholder('Ej. Lote preferido, producto en cuarentena temporal…')
                            ->rows(4)
                            ->maxLength(5000)
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
