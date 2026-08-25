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

final class CloseBranchDailyOperationAction
{
    public static function make(): Action
    {
        return Action::make('closeDailyOperation')
            ->label('Cerrar')
            ->icon(Heroicon::LockClosed)
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading('Cerrar sucursal')
            ->modalDescription('Antes de cerrar se validará que todas las cajas de la sucursal estén cerradas. Luego se enviará la conciliación global a administradores y gerentes asociados.')
            ->modalSubmitActionLabel('Confirmar cierre')
            ->visible(function (Branch $record): bool {
                $user = Auth::user();
                if (! $user instanceof User) {
                    return false;
                }

                return app(BranchDailyOperationService::class)->canClose($user, $record);
            })
            ->successNotificationTitle('Sucursal cerrada')
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
                    app(BranchDailyOperationService::class)->close($user, $record);
                } catch (BranchDailyOperationException $exception) {
                    Notification::make()
                        ->danger()
                        ->title('No se pudo cerrar la sucursal')
                        ->body($exception->getMessage())
                        ->send();
                    $action->halt();
                }
            });
    }
}
