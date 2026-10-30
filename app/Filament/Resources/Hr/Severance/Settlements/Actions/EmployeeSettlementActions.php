<?php

namespace App\Filament\Resources\Hr\Severance\Settlements\Actions;

use App\Enums\EmployeeSettlementStatus;
use App\Filament\Resources\Hr\Severance\Settlements\EmployeeSettlementResource;
use App\Filament\Resources\Hr\Severance\Settlements\Schemas\SettlementInputsSchema;
use App\Models\EmployeeSettlement;
use App\Models\EmployeeSettlementLine;
use App\Models\User;
use App\Services\Severance\EmployeeSettlementService;
use Closure;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Throwable;

final class EmployeeSettlementActions
{
    public static function pdf(): Action
    {
        return Action::make('pdf')
            ->label('PDF')
            ->icon(Heroicon::DocumentArrowDown)
            ->color('gray')
            ->url(fn (EmployeeSettlement $record): string => route('severance.settlement-pdf', $record), shouldOpenInNewTab: true);
    }

    public static function recalculate(): Action
    {
        return Action::make('recalculate')
            ->label('Editar datos')
            ->icon(Heroicon::PencilSquare)
            ->color('gray')
            ->modalHeading('Editar datos y recalcular')
            ->modalDescription('Al recalcular se pierden los ajustes manuales.')
            ->modalSubmitActionLabel('Recalcular')
            ->fillForm(fn (EmployeeSettlement $record): array => $record->only([
                'guarantee_accrued_usd', 'accrued_interest_usd', 'advances_usd', 'utilities_paid_usd',
                'pending_vacation_days', 'pending_bonus_days', 'unpaid_salary_days',
            ]))
            ->visible(fn (EmployeeSettlement $record): bool => $record->isDraft())
            ->schema(SettlementInputsSchema::fields())
            ->action(self::guarded('No se pudo recalcular', function (EmployeeSettlement $record, array $data): void {
                app(EmployeeSettlementService::class)->recalculate($record, $data);
                Notification::make()->title('Liquidación recalculada')->success()->send();
            }));
    }

    public static function adjustLine(): Action
    {
        return Action::make('adjustLine')
            ->label('Ajustar concepto')
            ->icon(Heroicon::AdjustmentsHorizontal)
            ->color('gray')
            ->modalSubmitActionLabel('Guardar ajuste')
            ->visible(fn (EmployeeSettlement $record): bool => $record->isDraft())
            ->schema([
                Select::make('line_id')
                    ->label('Concepto')
                    ->options(fn (EmployeeSettlement $record): array => $record->lines()->pluck('label', 'id')->all())
                    ->native(false)
                    ->required(),
                TextInput::make('amount_usd')->label('Monto correcto')->numeric()->minValue(0)->step(0.01)->prefix('US$')->required(),
                Textarea::make('reason')->label('Motivo del ajuste')->required()->rows(3)->maxLength(1000),
            ])
            ->action(self::guarded('No se pudo ajustar', function (EmployeeSettlement $record, array $data): void {
                $line = EmployeeSettlementLine::query()
                    ->where('employee_settlement_id', $record->id)
                    ->findOrFail($data['line_id']);

                app(EmployeeSettlementService::class)->adjustLine($line, (float) $data['amount_usd'], $data['reason'], self::user());
                Notification::make()->title('Concepto ajustado')->success()->send();
            }));
    }

    public static function submit(): Action
    {
        return Action::make('submit')
            ->label('Enviar a aprobación')
            ->icon(Heroicon::PaperAirplane)
            ->color('primary')
            ->requiresConfirmation()
            ->visible(fn (EmployeeSettlement $record): bool => $record->isDraft())
            ->action(self::guarded('No se pudo enviar', function (EmployeeSettlement $record): void {
                app(EmployeeSettlementService::class)->submit($record);
                Notification::make()->title('Enviada a aprobación')->success()->send();
            }));
    }

    public static function approve(): Action
    {
        return Action::make('approve')
            ->label('Aprobar')
            ->icon(Heroicon::CheckCircle)
            ->color('success')
            ->modalHeading('Aprobar liquidación')
            ->modalDescription('Los montos y la tasa BCV de hoy quedan fijos.')
            ->modalSubmitActionLabel('Aprobar')
            ->visible(fn (EmployeeSettlement $record): bool => self::isAdministrator() && $record->status === EmployeeSettlementStatus::PendingApproval)
            ->schema([
                TextInput::make('manual_rate')
                    ->label('Tasa manual (opcional)')
                    ->helperText('Solo se usa si no se puede obtener la tasa BCV.')
                    ->numeric()
                    ->minValue(0.01),
            ])
            ->action(self::guarded('No se pudo aprobar', function (EmployeeSettlement $record, array $data): void {
                app(EmployeeSettlementService::class)->approve(
                    $record,
                    self::user(),
                    filled($data['manual_rate'] ?? null) ? (float) $data['manual_rate'] : null,
                );
                Notification::make()->title('Liquidación aprobada')->success()->send();
            }));
    }

    public static function returnToDraft(): Action
    {
        return Action::make('returnToDraft')
            ->label('Devolver a borrador')
            ->icon(Heroicon::ArrowUturnLeft)
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription('Se borra la aprobación y la tasa fijada.')
            ->visible(fn (EmployeeSettlement $record): bool => self::isAdministrator()
                && in_array($record->status, [EmployeeSettlementStatus::PendingApproval, EmployeeSettlementStatus::Approved], true))
            ->action(self::guarded('No se pudo devolver', function (EmployeeSettlement $record): void {
                app(EmployeeSettlementService::class)->returnToDraft($record, self::user());
                Notification::make()->title('Devuelta a borrador')->success()->send();
            }));
    }

    public static function pay(): Action
    {
        return Action::make('pay')
            ->label('Registrar pago')
            ->icon(Heroicon::Banknotes)
            ->color('success')
            ->modalHeading('Registrar pago')
            ->modalDescription('Es definitivo: salda los préstamos, inactiva al empleado y cierra el egreso.')
            ->modalSubmitActionLabel('Registrar pago')
            ->visible(fn (EmployeeSettlement $record): bool => self::isAdministrator() && $record->status === EmployeeSettlementStatus::Approved)
            ->schema([
                TextInput::make('method')->label('Método de pago')->required()->maxLength(60)->placeholder('Transferencia, efectivo…'),
                TextInput::make('reference')->label('Referencia')->required()->maxLength(120),
            ])
            ->action(self::guarded('No se pudo registrar el pago', function (EmployeeSettlement $record, array $data): void {
                app(EmployeeSettlementService::class)->pay($record, $data['method'], $data['reference'], self::user());
                Notification::make()->title('Pago registrado')->body('El empleado quedó inactivo y el egreso cerrado.')->success()->send();
            }));
    }

    public static function cancel(): Action
    {
        return Action::make('cancelSettlement')
            ->label('Anular')
            ->icon(Heroicon::XCircle)
            ->color('danger')
            ->modalHeading('Anular liquidación')
            ->modalSubmitActionLabel('Anular')
            ->visible(fn (EmployeeSettlement $record): bool => self::isAdministrator()
                && ! in_array($record->status, [EmployeeSettlementStatus::Paid, EmployeeSettlementStatus::Cancelled], true))
            ->schema([
                Textarea::make('reason')->label('Motivo')->required()->rows(3)->maxLength(1000),
            ])
            ->action(self::guarded('No se pudo anular', function (EmployeeSettlement $record, array $data): void {
                app(EmployeeSettlementService::class)->cancel($record, $data['reason'], self::user());
                Notification::make()->title('Liquidación anulada')->success()->send();
            }));
    }

    private static function user(): User
    {
        return EmployeeSettlementResource::currentUser();
    }

    private static function isAdministrator(): bool
    {
        return EmployeeSettlementResource::currentUser()?->isAdministrator() ?? false;
    }

    private static function guarded(string $failureTitle, Closure $handler): Closure
    {
        return function (EmployeeSettlement $record, array $data = []) use ($failureTitle, $handler): void {
            try {
                $handler($record, $data);
            } catch (Throwable $exception) {
                Notification::make()->title($failureTitle)->body($exception->getMessage())->danger()->send();
            }
        };
    }
}
