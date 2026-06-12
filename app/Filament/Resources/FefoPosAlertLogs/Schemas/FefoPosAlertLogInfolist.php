<?php

namespace App\Filament\Resources\FefoPosAlertLogs\Schemas;

use App\Filament\Resources\Products\ProductResource;
use App\Filament\Resources\Sales\SaleResource;
use App\Models\FefoPosAlertLog;
use App\Support\Inventory\InventoryQuantityFormat;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class FefoPosAlertLogInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Alerta FEFO en caja')
                    ->description('Notificación emitida al detectar un lote por vencer en el POS.')
                    ->icon(Heroicon::BellAlert)
                    ->schema([
                        TextEntry::make('notified_at')
                            ->label('Fecha y hora de la alerta')
                            ->dateTime('d/m/Y H:i:s')
                            ->icon(Heroicon::Clock),
                        TextEntry::make('severity')
                            ->label('Tipo')
                            ->badge()
                            ->formatStateUsing(fn ($state): string => $state?->label() ?? '—')
                            ->color(fn ($state): string => $state?->badgeColor() ?? 'gray'),
                        TextEntry::make('days_until_expiry')
                            ->label('Días al vencimiento')
                            ->suffix(' días')
                            ->badge()
                            ->color(fn (FefoPosAlertLog $record): string => $record->severity?->badgeColor() ?? 'gray'),
                        TextEntry::make('expiration_month_year')
                            ->label('Lote sugerido (FEFO)')
                            ->badge()
                            ->color('primary'),
                        TextEntry::make('quantity_in_lot')
                            ->label('Unidades en lote')
                            ->formatStateUsing(fn (mixed $state): string => InventoryQuantityFormat::display($state)),
                        TextEntry::make('supplier_invoice_number')
                            ->label('Factura proveedor')
                            ->placeholder('—'),
                    ])
                    ->columns(3),
                Section::make('Producto y sucursal')
                    ->icon(Heroicon::Cube)
                    ->schema([
                        TextEntry::make('branch.name')
                            ->label('Sucursal')
                            ->badge()
                            ->color('info'),
                        TextEntry::make('product_name')
                            ->label('Producto')
                            ->columnSpanFull(),
                        TextEntry::make('product_code')
                            ->label('Código')
                            ->badge()
                            ->color('primary')
                            ->copyable(),
                        TextEntry::make('product.name')
                            ->label('Producto en catálogo')
                            ->url(fn (FefoPosAlertLog $record): ?string => $record->product_id
                                ? ProductResource::getUrl('view', ['record' => $record->product_id], isAbsolute: false)
                                : null)
                            ->color('primary'),
                    ])
                    ->columns(2),
                Section::make('Cajero')
                    ->icon(Heroicon::UserCircle)
                    ->schema([
                        TextEntry::make('user.name')
                            ->label('Nombre'),
                        TextEntry::make('user.email')
                            ->label('Correo')
                            ->copyable(),
                    ])
                    ->columns(2),
                Section::make('Seguimiento de venta')
                    ->description('Vinculación automática cuando el cajero completa una venta con el mismo producto.')
                    ->icon(Heroicon::ShoppingBag)
                    ->schema([
                        TextEntry::make('sale_number')
                            ->label('Número de venta')
                            ->placeholder('Sin venta vinculada')
                            ->badge()
                            ->color(fn (FefoPosAlertLog $record): string => $record->isLinkedToSale() ? 'success' : 'gray')
                            ->url(fn (FefoPosAlertLog $record): ?string => $record->sale_id
                                ? SaleResource::getUrl('view', ['record' => $record->sale_id], isAbsolute: false)
                                : null),
                        TextEntry::make('sold_at')
                            ->label('Fecha de venta')
                            ->dateTime('d/m/Y H:i:s')
                            ->placeholder('—'),
                        TextEntry::make('quantity_sold')
                            ->label('Cantidad vendida')
                            ->formatStateUsing(fn (mixed $state): string => filled($state)
                                ? InventoryQuantityFormat::display($state)
                                : '—'),
                        TextEntry::make('response_time')
                            ->label('Tiempo de respuesta')
                            ->state(function (FefoPosAlertLog $record): string {
                                if (! $record->isLinkedToSale()) {
                                    return 'Pendiente — el cajero aún no completó una venta con este producto';
                                }

                                $minutes = $record->minutesUntilSale();
                                if ($minutes === null) {
                                    return '—';
                                }

                                if ($minutes <= 0) {
                                    return 'Menos de 1 minuto después de la alerta';
                                }

                                return $minutes.' minuto(s) después de la alerta';
                            })
                            ->columnSpanFull(),
                    ])
                    ->columns(3),
            ]);
    }
}
