<?php

namespace App\Filament\Resources\FiscalPrinters\Actions;

use App\Enums\FiscalPrinterMode;
use App\Models\FiscalPrinter;
use App\Models\User;
use App\Services\Fiscal\FiscalPrinterActivationChecklist;
use App\Services\Fiscal\FiscalPrinterModeManager;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class ChangeFiscalPrinterModeAction
{
    public static function make(): Action
    {
        return Action::make('changeFiscalPrinterMode')
            ->label('Cambiar modo')
            ->icon(Heroicon::AdjustmentsHorizontal)
            ->color('primary')
            ->modalHeading(fn (FiscalPrinter $record): string => 'Modo de '.$record->name)
            ->modalDescription(function (FiscalPrinter $record): string {
                $failures = app(FiscalPrinterActivationChecklist::class)->failures($record);

                return 'Modo actual: '.$record->currentMode()->label().'. '
                    .($failures === []
                        ? 'La lista de chequeo está completa: puede activarse.'
                        : 'Para «Activa» falta: '.implode(' · ', $failures));
            })
            ->fillForm(fn (FiscalPrinter $record): array => ['mode' => $record->currentMode()->value])
            ->schema([
                Radio::make('mode')
                    ->label('Nuevo modo')
                    ->options(FiscalPrinterMode::options())
                    ->descriptions(collect(FiscalPrinterMode::cases())
                        ->mapWithKeys(fn (FiscalPrinterMode $mode): array => [$mode->value => $mode->description()])
                        ->all())
                    ->required()
                    ->live(),
                Toggle::make('force')
                    ->label('Forzar activación aunque falten requisitos')
                    ->helperText('Solo administradores. Queda registrado en auditoría.')
                    ->visible(fn (Get $get): bool => $get('mode') === FiscalPrinterMode::Active->value
                        && Auth::user() instanceof User
                        && Auth::user()->isAdministrator()),
            ])
            ->action(function (FiscalPrinter $record, array $data): void {
                $user = Auth::user();
                $force = (bool) ($data['force'] ?? false) && $user instanceof User && $user->isAdministrator();

                try {
                    app(FiscalPrinterModeManager::class)->change(
                        $record,
                        FiscalPrinterMode::from((string) $data['mode']),
                        (string) ($user?->email ?? $user?->name ?? 'sistema'),
                        $force,
                    );
                } catch (ValidationException $e) {
                    Notification::make()
                        ->title('No se pudo cambiar el modo')
                        ->body(collect($e->errors())->flatten()->implode(' '))
                        ->danger()
                        ->persistent()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title($record->name.' ahora está en modo «'.$record->currentMode()->label().'»')
                    ->body($record->currentMode()->description())
                    ->success()
                    ->send();
            });
    }
}
