<?php

namespace App\Filament\BusinessPartners\Resources\HistoricalOfMovements\Schemas;

use App\Filament\BusinessPartners\Resources\Orders\OrderResource;
use App\Models\HistoricalOfMovement;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class HistoricalOfMovementInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Consumo de crédito')
                    ->description('Registro automático al pasar un pedido a crédito a estado «En proceso». Coincide con las columnas del listado.')
                    ->icon(Heroicon::ChartBar)
                    ->iconColor('info')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                            'lg' => 3,
                        ])
                            ->schema([
                                TextEntry::make('order.order_number')
                                    ->label('Nº pedido')
                                    ->size('lg')
                                    ->weight('bold')
                                    ->icon(Heroicon::ClipboardDocumentList)
                                    ->iconColor('primary')
                                    ->placeholder('—')
                                    ->copyable()
                                    ->copyMessage('Número de pedido copiado')
                                    ->url(fn (HistoricalOfMovement $record): ?string => $record->order_id !== null
                                        ? OrderResource::getUrl('view', ['record' => $record->order_id])
                                        : null)
                                    ->openUrlInNewTab(false)
                                    ->columnSpan(['default' => 1, 'sm' => 2, 'lg' => 3]),
                                TextEntry::make('created_at')
                                    ->label('Fecha')
                                    ->dateTime('d/m/Y H:i')
                                    ->icon(Heroicon::CalendarDays)
                                    ->iconColor('gray')
                                    ->placeholder('—'),
                                TextEntry::make('total_quantity_products')
                                    ->label('Cantidad total')
                                    ->numeric(decimalPlaces: 2)
                                    ->icon(Heroicon::Cube)
                                    ->iconColor('gray')
                                    ->placeholder('—'),
                                TextEntry::make('total_cost')
                                    ->label('Consumo (pedido)')
                                    ->money('USD')
                                    ->icon(Heroicon::ArrowTrendingDown)
                                    ->iconColor('warning')
                                    ->placeholder('—'),
                                TextEntry::make('remaining_credit')
                                    ->label('Crédito disponible tras movimiento')
                                    ->money('USD')
                                    ->icon(Heroicon::Banknotes)
                                    ->iconColor('success')
                                    ->placeholder('—')
                                    ->columnSpan(['default' => 1, 'sm' => 2, 'lg' => 1]),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull(),

                Section::make('Auditoría')
                    ->icon(Heroicon::Clock)
                    ->iconColor('gray')
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'sm' => 2,
                        ])
                            ->schema([
                                TextEntry::make('updated_at')
                                    ->label('Última actualización del registro')
                                    ->dateTime('d/m/Y H:i')
                                    ->icon(Heroicon::ArrowPath)
                                    ->iconColor('gray')
                                    ->placeholder('—'),
                            ]),
                    ])
                    ->columns(1)
                    ->columnSpanFull()
                    ->collapsed(),
            ]);
    }
}
