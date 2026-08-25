<?php

namespace App\Filament\Resources\ConciliationBdvs\Tables;

use App\Models\Branch;
use App\Services\BdvConciliation\ManualBdvConciliationService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ConciliationBdvsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('conciliated_at')
                    ->label('Conciliado')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),
                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->searchable(),
                TextColumn::make('reference')
                    ->label('Referencia')
                    ->badge()
                    ->copyable()
                    ->copyMessage('Referencia copiada')
                    ->searchable(),
                TextColumn::make('amount')
                    ->label('Monto')
                    ->money('VES')
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('payer_document')
                    ->label('Doc. pagador')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('payer_phone')
                    ->label('Tel. pagador')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('destination_phone')
                    ->label('Tel. destino')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('origin_bank')
                    ->label('Banco origen')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('bdv_code')
                    ->label('Código BDV')
                    ->badge()
                    ->color(fn (?string $state): string => match (true) {
                        (string) $state === ManualBdvConciliationService::MANUAL_BDV_CODE => 'info',
                        in_array((string) $state, ['00', '01', '1000', '200'], true) => 'success',
                        default => 'warning',
                    })
                    ->toggleable(),
                TextColumn::make('environment')
                    ->label('Entorno')
                    ->badge()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('user.name')
                    ->label('Conciliado por')
                    ->placeholder('—')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('is_manual')
                    ->label('Conciliación Manual')
                    ->badge()
                    ->formatStateUsing(fn (bool $state): string => $state ? 'Sí' : 'No')
                    ->color(fn (bool $state): string => $state ? 'warning' : 'gray')
                    ->sortable(),
                TextColumn::make('created_at')
                    ->label('Registro')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('conciliated_at', 'desc')
            ->poll('10s')
            ->filters([
                SelectFilter::make('branch_id')
                    ->label('Sucursal')
                    ->options(fn (): array => Branch::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable(),
                SelectFilter::make('environment')
                    ->label('Entorno')
                    ->options([
                        'qa' => 'QA',
                        'production' => 'Producción',
                    ]),
                TernaryFilter::make('is_bdv_ok')
                    ->label('Estado BDV')
                    ->placeholder('Todos')
                    ->trueLabel('Solo exitosos')
                    ->falseLabel('Solo con alerta')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereIn('bdv_code', ['00', '01', '1000', '200']),
                        false: fn (Builder $query): Builder => $query->where(function (Builder $w): void {
                            $w->whereNull('bdv_code')
                                ->orWhere(function (Builder $inner): void {
                                    $inner->whereNotIn('bdv_code', ['00', '01', '1000', '200'])
                                        ->where('bdv_code', '!=', ManualBdvConciliationService::MANUAL_BDV_CODE);
                                });
                        }),
                        blank: fn (Builder $query): Builder => $query,
                    ),
                TernaryFilter::make('is_manual')
                    ->label('Conciliación Manual')
                    ->placeholder('Todas')
                    ->trueLabel('Solo manuales')
                    ->falseLabel('Solo por API BDV')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->where('is_manual', true),
                        false: fn (Builder $query): Builder => $query->where('is_manual', false),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->visible(fn (): bool => false),
                ]),
            ]);
    }
}
