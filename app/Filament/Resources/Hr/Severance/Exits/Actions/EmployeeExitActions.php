<?php

namespace App\Filament\Resources\Hr\Severance\Exits\Actions;

use App\Enums\EmployeeExitChecklistItem;
use App\Filament\Resources\Hr\Severance\Exits\EmployeeExitResource;
use App\Filament\Resources\Hr\Severance\Settlements\EmployeeSettlementResource;
use App\Filament\Resources\Hr\Severance\Settlements\Schemas\SettlementInputsSchema;
use App\Models\EmployeeExit;
use App\Services\Severance\EmployeeExitService;
use App\Services\Severance\EmployeeSettlementService;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Throwable;

final class EmployeeExitActions
{
    public static function checklist(): Action
    {
        return Action::make('checklist')
            ->label('Lista de salida')
            ->icon(Heroicon::ClipboardDocumentCheck)
            ->color('primary')
            ->modalHeading('Lista de salida')
            ->modalSubmitActionLabel('Guardar')
            ->fillForm(fn (EmployeeExit $record): array => ['items' => $record->checklist ?? []])
            ->visible(fn (EmployeeExit $record): bool => $record->isOpen())
            ->schema([
                CheckboxList::make('items')->hiddenLabel()->options(EmployeeExitChecklistItem::options()),
            ])
            ->action(function (EmployeeExit $record, array $data): void {
                try {
                    app(EmployeeExitService::class)->updateChecklist($record, $data['items'] ?? []);
                    Notification::make()->title('Lista guardada')->success()->send();
                } catch (Throwable $exception) {
                    Notification::make()->title('No se pudo guardar')->body($exception->getMessage())->danger()->send();
                }
            });
    }

    public static function createSettlement(): Action
    {
        return Action::make('createSettlement')
            ->label('Calcular liquidación')
            ->icon(Heroicon::Calculator)
            ->color('success')
            ->modalHeading('Calcular liquidación')
            ->modalDescription('El sistema no tiene los sueldos históricos: carga estos datos desde contabilidad. Si un dato no aplica, déjalo en cero.')
            ->modalSubmitActionLabel('Calcular')
            ->visible(fn (EmployeeExit $record): bool => $record->isOpen()
                && $record->activeSettlement() === null
                && EmployeeSettlementResource::canViewAny())
            ->schema(SettlementInputsSchema::fields())
            ->action(function (EmployeeExit $record, array $data, Page $livewire): void {
                try {
                    $settlement = app(EmployeeSettlementService::class)->createDraft($record, $data, EmployeeExitResource::currentUser());
                    Notification::make()->title('Liquidación calculada')->success()->send();
                    $livewire->redirect(EmployeeSettlementResource::getUrl('view', ['record' => $settlement]));
                } catch (Throwable $exception) {
                    Notification::make()->title('No se pudo calcular')->body($exception->getMessage())->danger()->send();
                }
            });
    }

    public static function viewSettlement(): Action
    {
        return Action::make('viewSettlement')
            ->label('Ver liquidación')
            ->icon(Heroicon::DocumentText)
            ->color('success')
            ->visible(fn (EmployeeExit $record): bool => $record->activeSettlement() !== null && EmployeeSettlementResource::canViewAny())
            ->url(fn (EmployeeExit $record): string => EmployeeSettlementResource::getUrl('view', ['record' => $record->activeSettlement()]));
    }

    public static function cancel(): Action
    {
        return Action::make('cancelExit')
            ->label('Cancelar egreso')
            ->icon(Heroicon::XCircle)
            ->color('danger')
            ->modalHeading('Cancelar egreso')
            ->modalSubmitActionLabel('Cancelar egreso')
            ->modalCancelActionLabel('Volver')
            ->visible(fn (EmployeeExit $record): bool => $record->isOpen() && (EmployeeExitResource::currentUser()?->isAdministrator() ?? false))
            ->schema([
                Textarea::make('reason')->label('Motivo')->required()->rows(3)->maxLength(1000),
            ])
            ->action(function (EmployeeExit $record, array $data): void {
                try {
                    app(EmployeeExitService::class)->cancel($record, $data['reason']);
                    Notification::make()->title('Egreso cancelado')->success()->send();
                } catch (Throwable $exception) {
                    Notification::make()->title('No se pudo cancelar')->body($exception->getMessage())->danger()->send();
                }
            });
    }
}
