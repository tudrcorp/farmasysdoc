<?php

namespace App\Filament\Resources\Hr\Loans\Tables;

use App\Enums\HrLoanFrequency;
use App\Enums\HrLoanStatus;
use App\Enums\HrPayCurrencyBucket;
use App\Filament\Resources\Hr\Loans\HrLoanResource;
use App\Models\HrLoan;
use App\Models\User;
use App\Services\Hr\HrLoanApprover;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Throwable;

class HrLoansTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['employee', 'branch']))
            ->columns([
                TextColumn::make('employee_name')
                    ->label('Empleado')
                    ->state(fn (HrLoan $record): string => $record->employee?->fullName() ?? '—')
                    ->searchable(query: function ($query, string $search): void {
                        $query->whereHas('employee', function ($q) use ($search): void {
                            $q->where('first_name', 'like', "%{$search}%")
                                ->orWhere('last_name', 'like', "%{$search}%")
                                ->orWhere('national_id', 'like', "%{$search}%");
                        });
                    }),
                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->sortable(),
                TextColumn::make('amount_usd')
                    ->label('Monto')
                    ->money('USD')
                    ->alignEnd(),
                TextColumn::make('remaining_usd')
                    ->label('Saldo')
                    ->money('USD')
                    ->alignEnd(),
                TextColumn::make('pay_currency_bucket')
                    ->label('Bolsillo')
                    ->badge()
                    ->formatStateUsing(fn (HrPayCurrencyBucket|string|null $state): string => $state instanceof HrPayCurrencyBucket
                        ? $state->label()
                        : (HrPayCurrencyBucket::tryFrom((string) $state)?->label() ?? '—'))
                    ->color(fn (HrPayCurrencyBucket|string|null $state): string => match (
                        $state instanceof HrPayCurrencyBucket ? $state : HrPayCurrencyBucket::tryFrom((string) $state)
                    ) {
                        HrPayCurrencyBucket::Usd => 'success',
                        HrPayCurrencyBucket::Ves => 'info',
                        default => 'gray',
                    }),
                TextColumn::make('frequency')
                    ->label('Frecuencia')
                    ->formatStateUsing(fn (HrLoanFrequency|string|null $state): string => $state instanceof HrLoanFrequency
                        ? $state->label()
                        : (HrLoanFrequency::tryFrom((string) $state)?->label() ?? '—')),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->formatStateUsing(fn (HrLoanStatus|string|null $state): string => $state instanceof HrLoanStatus
                        ? $state->label()
                        : (HrLoanStatus::tryFrom((string) $state)?->label() ?? '—'))
                    ->color(fn (HrLoanStatus|string|null $state): string => $state instanceof HrLoanStatus
                        ? $state->filamentColor()
                        : (HrLoanStatus::tryFrom((string) $state)?->filamentColor() ?? 'gray')),
                TextColumn::make('concept')
                    ->label('Concepto')
                    ->limit(30)
                    ->toggleable(),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Estatus')
                    ->options(HrLoanStatus::options()),
                SelectFilter::make('frequency')
                    ->label('Frecuencia')
                    ->options(HrLoanFrequency::options()),
                SelectFilter::make('branch_id')
                    ->label('Sucursal')
                    ->relationship('branch', 'name')
                    ->searchable()
                    ->preload(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (): bool => HrLoanResource::currentUser()?->isAdministrator() ?? false),
                Action::make('approve')
                    ->label('Aprobar')
                    ->icon(Heroicon::CheckCircle)
                    ->color('success')
                    ->requiresConfirmation()
                    ->visible(fn (HrLoan $record): bool => self::canDecide($record))
                    ->action(function (HrLoan $record): void {
                        try {
                            app(HrLoanApprover::class)->approve($record, self::adminUser());
                            Notification::make()->title('Préstamo aprobado')->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title('No se pudo aprobar')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('reject')
                    ->label('Rechazar')
                    ->icon(Heroicon::XCircle)
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (HrLoan $record): bool => self::canDecide($record))
                    ->action(function (HrLoan $record): void {
                        try {
                            app(HrLoanApprover::class)->reject($record, self::adminUser());
                            Notification::make()->title('Préstamo rechazado')->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title('No se pudo rechazar')->body($e->getMessage())->danger()->send();
                        }
                    }),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => HrLoanResource::currentUser()?->isAdministrator() ?? false),
                ]),
            ]);
    }

    private static function canDecide(HrLoan $record): bool
    {
        $user = HrLoanResource::currentUser();

        return $user instanceof User
            && $user->isAdministrator()
            && $record->status === HrLoanStatus::PendingApproval;
    }

    private static function adminUser(): User
    {
        $user = HrLoanResource::currentUser();
        if (! $user instanceof User) {
            throw new \RuntimeException('Usuario no autenticado.');
        }

        return $user;
    }
}
