<?php

namespace App\Filament\Resources\Hr\Employees\Actions;

use App\Models\Employee;
use App\Services\Hr\EmployeePortalAuthenticator;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;

final class ClearEmployeePortalPasswordAction
{
    public static function make(): Action
    {
        return Action::make('clearEmployeePortalPassword')
            ->label('Quitar clave del portal')
            ->icon(Heroicon::Key)
            ->color('gray')
            ->requiresConfirmation()
            ->modalHeading('Quitar la clave del portal')
            ->modalDescription('El empleado podrá volver a entrar solo con su cédula o teléfono. Él puede crear otra clave cuando quiera.')
            ->modalSubmitActionLabel('Quitar clave')
            ->visible(fn (?Employee $record): bool => $record instanceof Employee && $record->hasPortalPassword())
            ->action(function (Employee $record): void {
                app(EmployeePortalAuthenticator::class)->clearPassword($record);

                Notification::make()
                    ->title('Clave quitada')
                    ->body('El empleado ya puede entrar al portal sin clave.')
                    ->success()
                    ->send();
            });
    }
}
