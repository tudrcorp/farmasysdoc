<?php

namespace App\Filament\Resources\MedicationQuotes\Tables;

use App\Models\MedicationQuote;
use App\Services\Quotes\MedicationQuoteSender;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Js;

class MedicationQuotesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('number')
                    ->label('Cotización')
                    ->searchable()
                    ->sortable()
                    ->weight('semibold'),
                TextColumn::make('requester_name')
                    ->label('Solicitante')
                    ->searchable()
                    ->description(fn (MedicationQuote $record): string => $record->requester_document),
                TextColumn::make('total_usd')
                    ->label('Total')
                    ->alignEnd()
                    ->formatStateUsing(fn ($state): string => 'USD '.number_format((float) $state, 2, ',', '.'))
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('emailed_at')
                    ->label('Correo')
                    ->placeholder('Sin enviar')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(),
                TextColumn::make('whatsapped_at')
                    ->label('WhatsApp')
                    ->placeholder('Sin enviar')
                    ->dateTime('d/m/Y H:i')
                    ->toggleable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                Action::make('downloadPdf')
                    ->label('PDF')
                    ->icon(Heroicon::ArrowDownTray)
                    ->action(function (MedicationQuote $record, $livewire): void {
                        $url = URL::temporarySignedRoute(
                            'medication-quotes.pdf',
                            now()->addMinutes(10),
                            ['medicationQuote' => $record->getKey()],
                        );
                        $livewire->js('window.open('.Js::from($url).', "_blank")');
                    }),
                Action::make('sendQuote')
                    ->label('Enviar')
                    ->icon(Heroicon::PaperAirplane)
                    ->requiresConfirmation()
                    ->modalHeading('Enviar cotización')
                    ->modalDescription('Se enviará al correo y al WhatsApp del solicitante.')
                    ->action(function (MedicationQuote $record): void {
                        $notes = app(MedicationQuoteSender::class)->send($record);
                        Notification::make()
                            ->title('Envío de la cotización')
                            ->body(implode(' ', $notes))
                            ->success()
                            ->send();
                    }),
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
