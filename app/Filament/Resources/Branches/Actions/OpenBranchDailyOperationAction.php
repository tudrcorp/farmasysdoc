<?php

namespace App\Filament\Resources\Branches\Actions;

use App\Models\Branch;
use App\Models\User;
use App\Services\Branches\BranchDailyOperationService;
use App\Support\Branches\BranchDailyOperationException;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

final class OpenBranchDailyOperationAction
{
    public static function make(): Action
    {
        return Action::make('openDailyOperation')
            ->label('Aperturar')
            ->icon(Heroicon::LockOpen)
            ->color('success')
            ->requiresConfirmation()
            ->modalHeading('Aperturar sucursal')
            ->modalDescription('Se registrará la apertura de la gestión del día y se notificará a administradores y gerentes de esta sucursal.')
            ->modalSubmitActionLabel('Confirmar apertura')
            ->visible(function (Branch $record): bool {
                $user = Auth::user();
                if (! $user instanceof User) {
                    return false;
                }

                return app(BranchDailyOperationService::class)->canOpen($user, $record);
            })
            ->successNotificationTitle('Sucursal aperturada')
            ->action(function (Branch $record, Action $action): void {
                $user = Auth::user();
                if (! $user instanceof User) {
                    Notification::make()
                        ->danger()
                        ->title('Debe iniciar sesión.')
                        ->send();
                    $action->halt();

                    return;
                }

                try {
                    app(BranchDailyOperationService::class)->open($user, $record);
                } catch (BranchDailyOperationException $exception) {
                    Notification::make()
                        ->danger()
                        ->title('No se pudo aperturar la sucursal')
                        ->body($exception->getMessage())
                        ->send();
                    $action->halt();
                }
            });
    }
}
