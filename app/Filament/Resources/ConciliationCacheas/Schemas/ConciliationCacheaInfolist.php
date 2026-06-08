<?php

namespace App\Filament\Resources\ConciliationCacheas\Schemas;

use App\Enums\ConciliationCacheaCollectionStatus;
use App\Support\Sales\CacheaPosPaymentSupport;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class ConciliationCacheaInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Conciliación Cachea')
                    ->icon(Heroicon::Banknotes)
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 3,
                        ])
                            ->schema([
                                TextEntry::make('recorded_at')
                                    ->label('Registrado')
                                    ->dateTime('d/m/Y H:i:s'),
                                TextEntry::make('sale_number')
                                    ->label('Número de venta')
                                    ->copyable(),
                                TextEntry::make('branch.name')
                                    ->label('Sucursal'),
                                TextEntry::make('user.name')
                                    ->label('Cajero')
                                    ->placeholder('—'),
                                TextEntry::make('sale_total')
                                    ->label('Total venta')
                                    ->money('USD'),
                                TextEntry::make('cachea_paid_amount')
                                    ->label('Pagado con Cachea')
                                    ->money('USD'),
                                TextEntry::make('remainder')
                                    ->label('Resto · pendiente Cachea')
                                    ->money('USD')
                                    ->color('warning'),
                                TextEntry::make('collection_status')
                                    ->label('Estatus de cobro Cachea')
                                    ->badge()
                                    ->formatStateUsing(fn (?ConciliationCacheaCollectionStatus $state): string => $state?->label() ?? '—')
                                    ->color(fn (?ConciliationCacheaCollectionStatus $state): string => $state?->badgeColor() ?? 'gray'),
                                TextEntry::make('collection_status_at')
                                    ->label('Marcado recibido el')
                                    ->dateTime('d/m/Y H:i:s')
                                    ->placeholder('—')
                                    ->visible(fn ($record): bool => $record->collection_status === ConciliationCacheaCollectionStatus::AmountReceived),
                                TextEntry::make('collection_status_by')
                                    ->label('Marcado recibido por')
                                    ->placeholder('—')
                                    ->visible(fn ($record): bool => $record->collection_status === ConciliationCacheaCollectionStatus::AmountReceived),
                                TextEntry::make('complement_payment_method')
                                    ->label('Forma de pago del resto')
                                    ->formatStateUsing(fn (?string $state): string => CacheaPosPaymentSupport::complementLabel($state)),
                                TextEntry::make('reference')
                                    ->label('Referencia')
                                    ->placeholder('—')
                                    ->copyable(),
                                TextEntry::make('created_by')
                                    ->label('Registrado por')
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->columnSpanFull(),
            ]);
    }
}
