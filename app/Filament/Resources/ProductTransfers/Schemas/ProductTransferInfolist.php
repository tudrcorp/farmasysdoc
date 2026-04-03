<?php

namespace App\Filament\Resources\ProductTransfers\Schemas;

use App\Filament\Resources\Sales\SaleResource;
use App\Models\ProductTransfer;
use App\Models\ProductTransferItem;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View as SchemaView;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ProductTransferInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identificación')
                    ->icon(Heroicon::Hashtag)
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])
                            ->schema([
                                TextEntry::make('code')
                                    ->label('Código')
                                    ->copyable()
                                    ->icon(Heroicon::QrCode),
                                TextEntry::make('status')
                                    ->label('Estado')
                                    ->badge()
                                    ->formatStateUsing(fn (?string $state): string => match (strtolower((string) $state)) {
                                        'pending' => 'Pendiente',
                                        'in_progress' => 'En proceso',
                                        'completed' => 'Completado',
                                        'cancelled' => 'Cancelado',
                                        default => (string) $state,
                                    }),
                                TextEntry::make('transfer_type')
                                    ->label('Tipo')
                                    ->formatStateUsing(fn (?string $state): string => match (strtolower((string) $state)) {
                                        'internal' => 'Interno',
                                        'external' => 'Externo',
                                        'adjustment' => 'Ajuste',
                                        default => (string) $state,
                                    }),
                                TextEntry::make('total_transfer_cost')
                                    ->label('Costo total (traslado)')
                                    ->money('USD')
                                    ->placeholder('—')
                                    ->visible(fn (ProductTransfer $record): bool => $record->status === 'completed'),
                            ]),
                    ]),
                Section::make('Sucursales')
                    ->icon(Heroicon::ArrowsRightLeft)
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])
                            ->schema([
                                TextEntry::make('fromBranch.name')
                                    ->label('Origen'),
                                TextEntry::make('toBranch.name')
                                    ->label('Destino'),
                            ]),
                    ]),
                Section::make('Productos')
                    ->icon(Heroicon::Cube)
                    ->extraAttributes([
                        'class' => 'fi-ios-transfer-items-section',
                    ])
                    ->schema([
                        SchemaView::make('filament.infolists.components.product-transfer-items-ios')
                            ->columnSpanFull()
                            ->viewData(fn (ProductTransfer $record): array => [
                                'rows' => self::buildIosProductRows($record),
                            ]),
                    ]),
                Section::make('Auditoría')
                    ->icon(Heroicon::UserCircle)
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])
                            ->schema([
                                TextEntry::make('created_by')
                                    ->label('Registrado por'),
                                TextEntry::make('created_at')
                                    ->label('Fecha de registro')
                                    ->dateTime('d/m/Y H:i'),
                                TextEntry::make('completed_by')
                                    ->label('Completado por')
                                    ->placeholder('—'),
                                TextEntry::make('completed_at')
                                    ->label('Fecha de completado')
                                    ->dateTime('d/m/Y H:i')
                                    ->placeholder('—'),
                                TextEntry::make('updated_by')
                                    ->label('Última edición por'),
                                TextEntry::make('updated_at')
                                    ->label('Última edición')
                                    ->dateTime('d/m/Y H:i'),
                            ]),
                    ]),
                Section::make('Venta interna (emisora)')
                    ->description('Venta a valor de costo generada al completar el traslado en la sucursal de origen.')
                    ->icon(Heroicon::Banknotes)
                    ->visible(fn (ProductTransfer $record): bool => filled($record->sale_id))
                    ->schema([
                        TextEntry::make('sale.sale_number')
                            ->label('Número de venta')
                            ->url(fn (ProductTransfer $record): ?string => $record->sale_id !== null
                                ? SaleResource::getUrl('view', ['record' => $record->sale_id], isAbsolute: false)
                                : null)
                            ->openUrlInNewTab()
                            ->placeholder('—'),
                        TextEntry::make('sale.total')
                            ->label('Total venta (costo)')
                            ->money('USD')
                            ->placeholder('—'),
                    ]),
            ]);
    }

    /**
     * @return list<array{name: string, meta: string, quantity: string}>
     */
    private static function buildIosProductRows(ProductTransfer $record): array
    {
        return $record->items()
            ->with('product')
            ->orderBy('id')
            ->get()
            ->map(function (ProductTransferItem $item): array {
                $product = $item->product;
                $name = filled($product?->name) ? (string) $product->name : 'Producto sin nombre';
                $meta = filled($product?->barcode)
                    ? (string) $product->barcode
                    : (filled($product?->sku) ? (string) $product->sku : '—');

                return [
                    'name' => $name,
                    'meta' => $meta,
                    'quantity' => self::formatItemQuantity($item->quantity),
                ];
            })
            ->all();
    }

    private static function formatItemQuantity(mixed $state): string
    {
        if (! is_numeric($state)) {
            return '—';
        }

        return rtrim(rtrim(number_format((float) $state, 3, ',', '.'), '0'), ',');
    }
}
