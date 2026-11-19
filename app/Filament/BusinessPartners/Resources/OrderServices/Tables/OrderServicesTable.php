<?php

namespace App\Filament\BusinessPartners\Resources\OrderServices\Tables;

use App\Filament\BusinessPartners\Resources\OrderServices\OrderServiceResource;
use App\Models\OrderService;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrderServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('order'))
            ->defaultSort('ordered_at', 'desc')
            ->columns([
                TextColumn::make('service_order_number')
                    ->label('Orden')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary')
                    ->copyable(),
                TextColumn::make('order.order_number')
                    ->label('Pedido')
                    ->searchable()
                    ->placeholder('—')
                    ->copyable(),
                TextColumn::make('patient_name')
                    ->label('Paciente')
                    ->searchable()
                    ->placeholder('—')
                    ->wrap(),
                TextColumn::make('external_reference')
                    ->label('Referencia')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::statusLabel($state)),
                TextColumn::make('ordered_at')
                    ->label('Emitida')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->recordUrl(fn (OrderService $record): string => OrderServiceResource::getUrl('view', ['record' => $record], isAbsolute: false))
            ->recordActions([
                ViewAction::make()->icon(Heroicon::Eye),
            ]);
    }

    private static function statusLabel(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'borrador' => 'Borrador',
            'aprobada' => 'Aprobada',
            'en-proceso', 'en proceso' => 'En proceso',
            'finalizada' => 'Finalizada',
            'cancelada' => 'Cancelada',
            default => filled($status) ? (string) $status : '—',
        };
    }
}
