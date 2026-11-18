<?php

namespace App\Filament\Resources\Plans\Actions;

use App\Filament\Resources\Plans\PlanResource;
use App\Models\Plan;
use App\Models\User;
use App\Services\Partners\PlanMaintenance;
use App\Support\Finance\BcvRate;
use App\Support\Plans\PlanPremiumConverter;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;

class PlanActions
{
    public static function duplicate(): Action
    {
        return Action::make('duplicatePlan')
            ->label('Duplicar')
            ->icon(Heroicon::DocumentDuplicate)
            ->color('gray')
            ->visible(fn (): bool => PlanResource::canCreate())
            ->modalHeading('Duplicar plan')
            ->modalDescription('Se copian servicios, tarifas, catálogos, subplanes y baremo. La copia queda en borrador y sin aliados asignados.')
            ->fillForm(fn (Plan $record): array => [
                'code' => Plan::nextCode(),
                'name' => $record->name.' (copia)',
            ])
            ->schema([
                TextInput::make('code')
                    ->label('Código')
                    ->required()
                    ->maxLength(30)
                    ->unique(Plan::class, 'code')
                    ->validationMessages(['unique' => 'Ya existe un plan con ese código.']),
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (Plan $record, array $data, Action $action): void {
                $user = Auth::user();
                $copy = app(PlanMaintenance::class)->duplicate(
                    $record,
                    (string) $data['code'],
                    (string) $data['name'],
                    $user instanceof User ? $user : null,
                );

                Notification::make()->title('Plan duplicado como borrador')->success()->send();
                $action->redirect(PlanResource::getUrl('edit', ['record' => $copy]));
            });
    }

    public static function refreshVesPremiums(): Action
    {
        return Action::make('refreshVesPremiums')
            ->label('Actualizar Bs. con tasa BCV')
            ->icon(Heroicon::ArrowPath)
            ->color('warning')
            ->visible(fn (Plan $record): bool => PlanResource::canEdit($record))
            ->requiresConfirmation()
            ->modalHeading('Actualizar primas en bolívares')
            ->modalDescription(function (): string {
                $rate = PlanPremiumConverter::todayRate();

                return $rate === null
                    ? 'No se pudo obtener la tasa BCV de hoy. Intente más tarde.'
                    : 'Se recalculan todas las primas en Bs. del plan con la tasa BCV de hoy (Bs. '.BcvRate::format($rate).' por USD). Los montos en USD no cambian.';
            })
            ->modalSubmitActionLabel('Actualizar')
            ->action(function (Plan $record): void {
                $rate = PlanPremiumConverter::todayRate();

                if ($rate === null) {
                    Notification::make()->title('No se pudo obtener la tasa BCV de hoy')->danger()->send();

                    return;
                }

                $user = Auth::user();
                $count = app(PlanMaintenance::class)->refreshVesPremiums($record, $rate, $user instanceof User ? $user : null);

                Notification::make()
                    ->title('Primas en Bs. actualizadas')
                    ->body("{$count} prima(s) recalculada(s) con tasa Bs. ".BcvRate::format($rate).'.')
                    ->success()
                    ->send();
            });
    }
}
