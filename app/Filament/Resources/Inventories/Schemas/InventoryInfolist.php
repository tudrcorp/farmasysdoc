<?php

namespace App\Filament\Resources\Inventories\Schemas;

use App\Models\Inventory;
use Filament\Infolists\Components\IconEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class InventoryInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Sucursal y producto')
                    ->description('Combinación única de almacén y artículo en inventario.')
                    ->icon(Heroicon::BuildingStorefront)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('branch.name')
                                    ->label('Sucursal')
                                    ->icon(Heroicon::BuildingOffice2),
                                TextEntry::make('product.name')
                                    ->label('Producto')
                                    ->icon(Heroicon::Cube),
                                TextEntry::make('product.barcode')
                                    ->label('Código del producto')
                                    ->getStateUsing(fn (Inventory $record): string => filled($record->product?->barcode)
                                        ? (string) $record->product->barcode
                                        : '000'.$record->product_id)
                                    ->icon(Heroicon::QrCode)
                                    ->copyable(),
                                TextEntry::make('productCategory.name')
                                    ->label('Categoría (catálogo)')
                                    ->placeholder('—')
                                    ->icon(Heroicon::Swatch),
                                TextEntry::make('active_ingredient')
                                    ->label('Principio(s) activo(s)')
                                    ->placeholder('—')
                                    ->getStateUsing(function (Inventory $record): ?string {
                                        if (! is_array($record->active_ingredient) || $record->active_ingredient === []) {
                                            return null;
                                        }

                                        $ingredients = array_values(array_filter(
                                            $record->active_ingredient,
                                            fn (mixed $value): bool => is_string($value) && filled($value),
                                        ));

                                        return $ingredients !== [] ? implode(', ', $ingredients) : null;
                                    })
                                    ->columnSpanFull()
                                    ->icon(Heroicon::Beaker),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Existencias')
                    ->description('Cantidades físicas, reservas y disponibilidad para venta.')
                    ->icon(Heroicon::ArchiveBox)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('quantity')
                                    ->label('Existencias actuales')
                                    ->numeric(decimalPlaces: 0)
                                    ->icon(Heroicon::SquaresPlus),
                                TextEntry::make('reserved_quantity')
                                    ->label('Cantidad reservada')
                                    ->numeric(decimalPlaces: 0)
                                    ->icon(Heroicon::LockClosed),
                            ]),
                        TextEntry::make('available_for_sale')
                            ->label('Disponible para venta')
                            ->helperText('Existencias menos reservado; respeta la política de saldo negativo.')
                            ->getStateUsing(fn (Inventory $record): string => number_format($record->available_quantity, 3, ',', '.'))
                            ->icon(Heroicon::ShoppingCart),
                        IconEntry::make('allow_negative_stock')
                            ->label('Permitir saldo negativo')
                            ->boolean(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Precios (snapshot en inventario)')
                    ->description('Costos y precios calculados al registrar/actualizar inventario desde compras, usando IVA del sistema y % de categoría.')
                    ->icon(Heroicon::CurrencyDollar)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 3,
                        ])
                            ->schema([
                                TextEntry::make('cost_price')
                                    ->label('Costo')
                                    ->money('USD')
                                    ->placeholder('—')
                                    ->icon(Heroicon::ReceiptPercent),
                                TextEntry::make('vat_cost_amount')
                                    ->label('Valor del IVA (costo)')
                                    ->placeholder('—')
                                    ->money('USD')
                                    ->icon(Heroicon::Calculator),
                                TextEntry::make('cost_plus_vat')
                                    ->label('Costo + IVA')
                                    ->placeholder('—')
                                    ->money('USD')
                                    ->icon(Heroicon::PlusCircle),
                                TextEntry::make('final_price_without_vat')
                                    ->label('Precio final sin IVA')
                                    ->placeholder('—')
                                    ->money('USD')
                                    ->icon(Heroicon::Banknotes),
                                TextEntry::make('vat_final_price_amount')
                                    ->label('Valor IVA precio final')
                                    ->placeholder('—')
                                    ->money('USD')
                                    ->icon(Heroicon::Calculator),
                                TextEntry::make('final_price_with_vat')
                                    ->label('Precio final con IVA')
                                    ->placeholder('—')
                                    ->money('USD')
                                    ->icon(Heroicon::CurrencyDollar),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Políticas y ubicación')
                    ->description('Umbrales de reposición y ubicación física en la sucursal.')
                    ->icon(Heroicon::MapPin)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 3,
                        ])
                            ->schema([
                                TextEntry::make('reorder_point')
                                    ->label('Punto de reorden')
                                    ->placeholder('—')
                                    ->numeric(3)
                                    ->icon(Heroicon::BellAlert),
                                TextEntry::make('minimum_stock')
                                    ->label('Stock mínimo deseado')
                                    ->placeholder('—')
                                    ->numeric(3)
                                    ->icon(Heroicon::ArrowTrendingDown),
                                TextEntry::make('maximum_stock')
                                    ->label('Stock máximo sugerido')
                                    ->placeholder('—')
                                    ->numeric(3)
                                    ->icon(Heroicon::ArrowTrendingUp),
                            ]),
                        TextEntry::make('storage_location')
                            ->label('Ubicación física')
                            ->placeholder('—')
                            ->icon(Heroicon::QrCode)
                            ->columnSpanFull(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Auditoría de movimientos')
                    ->description('Fechas de referencia por movimientos y conteos físicos.')
                    ->icon(Heroicon::Clock)
                    ->collapsed()
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('last_movement_at')
                                    ->label('Último movimiento')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—'),
                                TextEntry::make('last_stock_take_at')
                                    ->label('Último conteo / auditoría')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Notas internas')
                    ->description('Observaciones solo para el equipo.')
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->collapsed()
                    ->schema([
                        TextEntry::make('notes')
                            ->label('Observaciones')
                            ->placeholder('—')
                            ->columnSpanFull()
                            ->prose(),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Auditoría del registro')
                    ->description('Quién creó o modificó la ficha de inventario.')
                    ->icon(Heroicon::UserGroup)
                    ->collapsed()
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('created_by')
                                    ->label('Creado por')
                                    ->placeholder('—')
                                    ->icon(Heroicon::User),
                                TextEntry::make('updated_by')
                                    ->label('Última modificación por')
                                    ->placeholder('—')
                                    ->icon(Heroicon::UserCircle),
                                TextEntry::make('created_at')
                                    ->label('Fecha de creación')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—'),
                                TextEntry::make('updated_at')
                                    ->label('Última actualización')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
