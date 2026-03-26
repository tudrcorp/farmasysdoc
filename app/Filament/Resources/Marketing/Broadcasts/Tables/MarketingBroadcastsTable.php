<?php

namespace App\Filament\Resources\Marketing\Broadcasts\Tables;

use App\Enums\MarketingBroadcastSendMode;
use App\Enums\MarketingBroadcastStatus;
use App\Enums\MarketingBroadcastType;
use App\Jobs\ProcessMarketingBroadcastJob;
use App\Models\MarketingBroadcast;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class MarketingBroadcastsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('type')
                    ->label('Tipo')
                    ->formatStateUsing(fn ($state): string => MarketingBroadcastType::tryLabel($state)),
                TextColumn::make('send_mode')
                    ->label('Audiencia')
                    ->formatStateUsing(fn ($state): string => MarketingBroadcastSendMode::tryLabel($state)),
                TextColumn::make('status')
                    ->label('Estado')
                    ->formatStateUsing(fn ($state): string => MarketingBroadcastStatus::tryLabel($state))
                    ->badge(),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                Action::make('sendNow')
                    ->label('Enviar')
                    ->icon(Heroicon::PaperAirplane)
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('¿Enviar esta difusión?')
                    ->modalDescription('Se pondrá en cola y se enviará a los destinatarios según la audiencia y canales elegidos.')
                    ->visible(fn (MarketingBroadcast $record): bool => in_array($record->status, [
                        MarketingBroadcastStatus::Draft,
                        MarketingBroadcastStatus::Scheduled,
                        MarketingBroadcastStatus::Failed,
                    ], true))
                    ->action(function (MarketingBroadcast $record): void {
                        ProcessMarketingBroadcastJob::dispatch($record);
                        Notification::make()
                            ->title('Envío en cola')
                            ->body('El job se ejecutará en segundo plano.')
                            ->success()
                            ->send();
                    }),
            ])
            ->defaultSort('updated_at', 'desc');
    }
}
