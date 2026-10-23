<?php

namespace App\Filament\Resources\PettyCashFunds\Tables;

use App\Enums\PettyCashFundStatus;
use App\Filament\Resources\PettyCashFunds\PettyCashFundResource;
use App\Filament\Resources\PettyCashFunds\Support\PettyCashFundActions;
use App\Models\PettyCashFund;
use App\Support\Finance\PettyCashMoney;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class PettyCashFundsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn ($query) => $query->with(['branch', 'requestedBy']))
            ->columns([
                TextColumn::make('requested_at')
                    ->label('Solicitada')
                    ->date('d/m/Y')
                    ->sortable(),
                TextColumn::make('requestedBy.name')
                    ->label('Gerente')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('requested_amount')
                    ->label('Desembolso')
                    ->state(fn (PettyCashFund $record): string => PettyCashMoney::formatUsd($record->transferAmountUsd()))
                    ->description(function (PettyCashFund $record): string {
                        $disbursementVes = PettyCashMoney::formatVes($record->transferAmountVes());
                        $totalVes = PettyCashMoney::formatVes((float) $record->amount_ves);

                        if (abs($record->transferAmountVes() - (float) $record->amount_ves) > 0.009) {
                            return $disbursementVes.' · Total fondo '.$totalVes;
                        }

                        return $disbursementVes;
                    })
                    ->alignEnd(),
                TextColumn::make('remaining_usd')
                    ->label('Disponible')
                    ->state(fn (PettyCashFund $record): string => PettyCashMoney::formatUsd((float) $record->remaining_usd))
                    ->description(fn (PettyCashFund $record): string => PettyCashMoney::formatVes((float) $record->remaining_ves))
                    ->alignEnd()
                    ->color(fn (PettyCashFund $record): string => (float) $record->remaining_usd > 0.009 ? 'success' : 'gray'),
                TextColumn::make('status')
                    ->label('Estatus')
                    ->badge()
                    ->formatStateUsing(fn (PettyCashFundStatus|string|null $state): string => $state instanceof PettyCashFundStatus
                        ? $state->label()
                        : (PettyCashFundStatus::tryFrom((string) $state)?->label() ?? '—'))
                    ->color(fn (PettyCashFundStatus|string|null $state): string => $state instanceof PettyCashFundStatus
                        ? $state->filamentColor()
                        : (PettyCashFundStatus::tryFrom((string) $state)?->filamentColor() ?? 'gray')),
                TextColumn::make('payment_reference')
                    ->label('Referencia')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('notes')
                    ->label('Motivo')
                    ->limit(40)
                    ->toggleable(),
            ])
            ->defaultSort('requested_at', 'desc')
            ->filters([
                SelectFilter::make('status')
                    ->label('Estatus')
                    ->options(PettyCashFundStatus::options()),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make()
                    ->visible(fn (PettyCashFund $record): bool => PettyCashFundResource::canEdit($record)),
                PettyCashFundActions::approve(),
                PettyCashFundActions::reject(),
                PettyCashFundActions::registerExpense(),
                PettyCashFundActions::replenish(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => PettyCashFundResource::currentUser()?->isAdministrator() ?? false),
                ]),
            ])
            ->emptyStateHeading('Sin solicitudes de caja chica')
            ->emptyStateDescription('Gerencia puede solicitar un fondo. Administración lo aprueba con comprobante y luego se registran los gastos hasta agotarlo.');
    }
}
