<?php

namespace App\Filament\Resources\Marketing\Broadcasts\Pages;

use App\Enums\MarketingBroadcastStatus;
use App\Filament\Resources\Marketing\Broadcasts\MarketingBroadcastResource;
use App\Jobs\ProcessMarketingBroadcastJob;
use App\Models\MarketingBroadcast;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ViewRecord;
use Filament\Support\Icons\Heroicon;

class ViewMarketingBroadcast extends ViewRecord
{
    protected static string $resource = MarketingBroadcastResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendNow')
                ->label('Enviar ahora')
                ->icon(Heroicon::PaperAirplane)
                ->color('success')
                ->requiresConfirmation()
                ->modalHeading('¿Enviar esta difusión?')
                ->visible(fn (): bool => $this->canDispatch($this->getRecord()))
                ->action(function (): void {
                    /** @var MarketingBroadcast $record */
                    $record = $this->getRecord();
                    ProcessMarketingBroadcastJob::dispatch($record);
                    Notification::make()
                        ->title('Envío en cola')
                        ->body('Revise los logs o la cola si no hay worker activo.')
                        ->success()
                        ->send();
                }),
            EditAction::make(),
        ];
    }

    protected function canDispatch(MarketingBroadcast $record): bool
    {
        return in_array($record->status, [
            MarketingBroadcastStatus::Draft,
            MarketingBroadcastStatus::Scheduled,
            MarketingBroadcastStatus::Failed,
        ], true);
    }
}
