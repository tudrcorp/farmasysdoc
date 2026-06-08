<?php

namespace App\Filament\Resources\InventoryStockFailures\Schemas;

use App\Filament\Resources\Products\ProductResource;
use App\Models\InventoryStockFailure;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class InventoryStockFailureInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Evento en caja')
                    ->description('Intento de venta con existencia cero detectado en la caja registradora.')
                    ->icon(Heroicon::ExclamationTriangle)
                    ->schema([
                        TextEntry::make('id')
                            ->label('ID registro')
                            ->badge()
                            ->color('gray'),
                        TextEntry::make('created_at')
                            ->label('Fecha y hora')
                            ->dateTime('d/m/Y H:i')
                            ->icon(Heroicon::Clock),
                        TextEntry::make('quantity')
                            ->label('Existencia reportada')
                            ->formatStateUsing(fn (mixed $state): string => number_format((float) $state, 3, ',', '.'))
                            ->badge()
                            ->color('danger')
                            ->icon(Heroicon::ArchiveBoxXMark),
                    ])
                    ->columns(3),
                Section::make('Producto y sucursal')
                    ->description('Datos capturados al momento del escaneo o búsqueda.')
                    ->icon(Heroicon::Cube)
                    ->schema([
                        TextEntry::make('branch.name')
                            ->label('Sucursal')
                            ->badge()
                            ->color('info')
                            ->icon(Heroicon::BuildingStorefront),
                        TextEntry::make('product_name')
                            ->label('Producto')
                            ->icon(Heroicon::Tag)
                            ->columnSpanFull(),
                        TextEntry::make('product_code')
                            ->label('Código del producto')
                            ->badge()
                            ->color('primary')
                            ->copyable()
                            ->copyMessage('Código copiado'),
                        TextEntry::make('product.name')
                            ->label('Producto en catálogo')
                            ->placeholder('—')
                            ->url(fn (InventoryStockFailure $record): ?string => $record->product_id
                                ? ProductResource::getUrl('view', ['record' => $record->product_id], isAbsolute: false)
                                : null)
                            ->color('primary')
                            ->visible(fn (InventoryStockFailure $record): bool => $record->product_id > 0),
                    ])
                    ->columns(2),
                Section::make('Usuario')
                    ->description('Cajero que intentó agregar el producto al carrito.')
                    ->icon(Heroicon::UserCircle)
                    ->schema([
                        TextEntry::make('user.name')
                            ->label('Nombre')
                            ->icon(Heroicon::User),
                        TextEntry::make('user.email')
                            ->label('Correo')
                            ->icon(Heroicon::Envelope)
                            ->copyable()
                            ->copyMessage('Correo copiado'),
                    ])
                    ->columns(2),
            ]);
    }
}
