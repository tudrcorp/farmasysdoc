<?php

namespace App\Filament\Resources\InventoryAdjustments\Schemas;

use App\Filament\Resources\InventoryMovements\InventoryMovementResource;
use App\Models\InventoryAdjustment;
use App\Support\Inventory\InventoryAdjustmentReason;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class InventoryAdjustmentInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Resumen del ajuste')
                    ->description('Cantidad y contexto registrados en inventario.')
                    ->icon(Heroicon::AdjustmentsHorizontal)
                    ->schema([
                        TextEntry::make('id')
                            ->label('ID ajuste')
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('reason')
                            ->label('Motivo')
                            ->badge()
                            ->formatStateUsing(fn (?string $state): string => InventoryAdjustmentReason::label($state))
                            ->color(fn (?string $state): string => InventoryAdjustmentReason::filamentColor($state)),
                        TextEntry::make('quantity_delta')
                            ->label('Delta cantidad')
                            ->formatStateUsing(function (mixed $state): string {
                                $n = (float) $state;
                                $sign = $n > 0 ? '+' : '';

                                return $sign.number_format($n, 3, ',', '.').' u';
                            })
                            ->color(function (InventoryAdjustment $record): string {
                                $n = (float) $record->quantity_delta;

                                return $n < -0.0001 ? 'danger' : ($n > 0.0001 ? 'success' : 'gray');
                            }),
                        TextEntry::make('unit_cost_snapshot')
                            ->label('Costo unitario (referencia)')
                            ->money('USD')
                            ->placeholder('—'),
                        TextEntry::make('created_at')
                            ->label('Fecha y hora')
                            ->dateTime('d/m/Y H:i')
                            ->icon(Heroicon::Clock),
                        TextEntry::make('created_by')
                            ->label('Registró')
                            ->placeholder('—')
                            ->icon(Heroicon::User),
                        TextEntry::make('notes')
                            ->label('Notas')
                            ->columnSpanFull()
                            ->placeholder('Sin notas')
                            ->prose(),
                    ])
                    ->columns(2),
                Section::make('Documento y ubicación')
                    ->description('Compra origen, sucursal, producto y movimiento vinculado.')
                    ->icon(Heroicon::MapPin)
                    ->schema([
                        TextEntry::make('purchase.purchase_number')
                            ->label('Orden de compra')
                            ->placeholder('—')
                            ->icon(Heroicon::ShoppingCart)
                            ->url(fn (InventoryAdjustment $record): ?string => $record->purchase_id
                                ? route('purchases.document-pdf', ['purchase' => $record->purchase_id])
                                : null)
                            ->openUrlInNewTab(),
                        TextEntry::make('branch.name')
                            ->label('Sucursal')
                            ->icon(Heroicon::BuildingStorefront),
                        TextEntry::make('product.name')
                            ->label('Producto')
                            ->columnSpanFull()
                            ->icon(Heroicon::Cube),
                        TextEntry::make('product.sku')
                            ->label('SKU')
                            ->placeholder('—'),
                        TextEntry::make('product.barcode')
                            ->label('Código de barras')
                            ->placeholder('—'),
                        TextEntry::make('inventory_movement_id')
                            ->label('Movimiento de inventario')
                            ->placeholder('—')
                            ->icon(Heroicon::ArrowPath)
                            ->url(fn (InventoryAdjustment $record): ?string => $record->inventory_movement_id
                                ? InventoryMovementResource::getUrl('view', ['record' => $record->inventory_movement_id], isAbsolute: false)
                                : null)
                            ->openUrlInNewTab(false),
                    ])
                    ->columns(2),
            ]);
    }
}
