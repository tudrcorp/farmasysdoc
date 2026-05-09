<?php

namespace App\Filament\Resources\ProductTransferSales\Tables;

use App\Enums\ProductTransferStatus;
use App\Filament\Resources\ProductTransfers\ProductTransferResource;
use App\Filament\Resources\ProductTransferSales\ProductTransferSaleResource;
use App\Models\ProductTransfer;
use Filament\Actions\ActionGroup;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductTransferSalesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'items',
                'fromBranch',
                'toBranch',
                'deliveryUser',
            ]))
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Código copiado')
                    ->icon(Heroicon::Hashtag),
                TextColumn::make('fromBranch.name')
                    ->label('Origen')
                    ->sortable()
                    ->searchable()
                    ->icon(Heroicon::BuildingStorefront),
                TextColumn::make('toBranch.name')
                    ->label('Destino')
                    ->sortable()
                    ->searchable()
                    ->icon(Heroicon::MapPin),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (mixed $state): string => ProductTransferStatus::labelForStored(
                        $state instanceof ProductTransferStatus ? $state : (filled($state) ? (string) $state : null),
                    ))
                    ->color(fn (mixed $state): string => ProductTransferStatus::filamentColorForStored(
                        $state instanceof ProductTransferStatus ? $state : (filled($state) ? (string) $state : null),
                    ))
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Registro')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->icon(Heroicon::CalendarDays),
            ])
            ->recordActions([
                ActionGroup::make([
                    ViewAction::make()
                        ->label('Ver traslado de venta')
                        ->icon(Heroicon::Eye),
                    ProductTransferResource::takeTransferAction(),
                    ProductTransferResource::markCompletedAction(),
                    ProductTransferResource::adminChangeStatusAction(),
                ]),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordUrl(fn (ProductTransfer $record): string => ProductTransferSaleResource::getUrl('view', ['record' => $record], isAbsolute: false));
    }
}
