<?php

namespace App\Filament\Resources\InventoryMovements\Schemas;

use App\Enums\InventoryMovementType;
use App\Models\Inventory;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Builder;

class InventoryMovementForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Producto e inventario')
                    ->description('Vincula el movimiento al producto y al registro de existencias en sucursal.')
                    ->icon(Heroicon::Cube)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
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
                                    ->prefixIcon(Heroicon::Cube),
                                Select::make('inventory_id')
                                    ->label('Registro de inventario')
                                    ->relationship(
                                        name: 'inventory',
                                        titleAttribute: 'id',
                                        modifyQueryUsing: fn (Builder $query) => $query->with(['branch', 'product'])->orderByDesc('id'),
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->native(false)
                                    ->getOptionLabelFromRecordUsing(function (Inventory $record): string {
                                        $product = $record->product?->name ?? '—';
                                        $branch = $record->branch?->name ?? '—';

                                        return '#'.$record->id.' — '.$product.' ('.$branch.')';
                                    })
                                    ->helperText('Existencias por sucursal y producto.')
                                    ->prefixIcon(Heroicon::ArchiveBox),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Tipo y cantidades')
                    ->description('Tipo de operación, cantidad del movimiento y costo unitario si aplica.')
                    ->icon(Heroicon::Calculator)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 3,
                        ])
                            ->schema([
                                Select::make('movement_type')
                                    ->label('Tipo de movimiento')
                                    ->options(InventoryMovementType::options())
                                    ->required()
                                    ->native(false)
                                    ->prefixIcon(Heroicon::ArrowsRightLeft),
                                TextInput::make('quantity')
                                    ->label('Cantidad')
                                    ->required()
                                    ->numeric()
                                    ->prefixIcon(Heroicon::Hashtag),
                                TextInput::make('unit_cost')
                                    ->label('Costo unitario')
                                    ->numeric()
                                    ->prefix('$')
                                    ->prefixIcon(Heroicon::Banknotes)
                                    ->helperText('Opcional según el tipo de movimiento.'),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Lote y fecha de vencimiento')
                    ->description('Trazabilidad por lote cuando el producto lo requiere.')
                    ->icon(Heroicon::CalendarDays)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextInput::make('batch_number')
                                    ->label('Número de lote')
                                    ->prefixIcon(Heroicon::QrCode),
                                DatePicker::make('expiry_date')
                                    ->label('Fecha de vencimiento')
                                    ->native(false)
                                    ->prefixIcon(Heroicon::Clock),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Referencia del documento')
                    ->description('Origen polimórfico (pedido, compra, ajuste, etc.).')
                    ->icon(Heroicon::Link)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextInput::make('reference_type')
                                    ->label('Tipo de referencia (clase)')
                                    ->placeholder('Ej. App\\Models\\Order')
                                    ->prefixIcon(Heroicon::DocumentText),
                                TextInput::make('reference_id')
                                    ->label('ID de referencia')
                                    ->numeric()
                                    ->prefixIcon(Heroicon::Hashtag),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull()
                    ->collapsed(),

                Section::make('Notas y auditoría')
                    ->description('Observaciones y usuario que registra el movimiento.')
                    ->icon(Heroicon::PencilSquare)
                    ->schema([
                        Textarea::make('notes')
                            ->label('Notas')
                            ->rows(3)
                            ->columnSpanFull(),
                        TextInput::make('created_by')
                            ->label('ID usuario creador')
                            ->numeric()
                            ->prefixIcon(Heroicon::User)
                            ->helperText('Identificador del usuario en el sistema, si aplica.'),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
