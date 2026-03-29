<?php

namespace App\Filament\Resources\InventoryMovements\Schemas;

use App\Enums\InventoryMovementType;
use App\Models\InventoryMovement;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class InventoryMovementInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Producto e inventario')
                    ->description('Artículo y registro de existencias asociado.')
                    ->icon(Heroicon::Cube)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('product.name')
                                    ->label('Producto')
                                    ->icon(Heroicon::Cube),
                                TextEntry::make('inventory_id')
                                    ->label('Registro de inventario')
                                    ->icon(Heroicon::ArchiveBox)
                                    ->formatStateUsing(function ($state, InventoryMovement $record): string {
                                        $inv = $record->inventory;
                                        if (! $inv) {
                                            return '—';
                                        }

                                        $product = $inv->product?->name ?? '—';
                                        $branch = $inv->branch?->name ?? '—';

                                        return '#'.$inv->id.' — '.$product.' ('.$branch.')';
                                    }),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Tipo y cantidades')
                    ->description('Operación registrada y valores.')
                    ->icon(Heroicon::Calculator)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 3,
                        ])
                            ->schema([
                                TextEntry::make('movement_type')
                                    ->label('Tipo de movimiento')
                                    ->badge()
                                    ->formatStateUsing(fn ($state): string => InventoryMovementType::tryLabel($state))
                                    ->icon(Heroicon::ArrowsRightLeft),
                                TextEntry::make('quantity')
                                    ->label('Cantidad')
                                    ->numeric(3)
                                    ->icon(Heroicon::Hashtag),
                                TextEntry::make('unit_cost')
                                    ->label('Costo unitario')
                                    ->money()
                                    ->placeholder('—')
                                    ->icon(Heroicon::Banknotes),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Lote y vencimiento')
                    ->icon(Heroicon::CalendarDays)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('batch_number')
                                    ->label('Número de lote')
                                    ->placeholder('—')
                                    ->icon(Heroicon::QrCode),
                                TextEntry::make('expiry_date')
                                    ->label('Fecha de vencimiento')
                                    ->date()
                                    ->placeholder('—')
                                    ->icon(Heroicon::Clock),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Notas y fechas')
                    ->icon(Heroicon::PencilSquare)
                    ->schema([
                        TextEntry::make('notes')
                            ->label('Notas')
                            ->placeholder('—')
                            ->columnSpanFull(),
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('created_by')
                                    ->label('ID usuario creador')
                                    ->placeholder('—')
                                    ->icon(Heroicon::User),
                                TextEntry::make('created_at')
                                    ->label('Creado')
                                    ->dateTime()
                                    ->icon(Heroicon::Calendar),
                                TextEntry::make('updated_at')
                                    ->label('Actualizado')
                                    ->dateTime()
                                    ->icon(Heroicon::ArrowPath),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),
            ]);
    }
}
