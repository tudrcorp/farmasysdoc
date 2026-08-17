<?php

namespace App\Filament\Resources\PosTerminals\Tables;

use App\Enums\VenezuelanPagoMovilBank;
use App\Filament\Resources\PosTerminals\PosTerminalResource;
use App\Models\Branch;
use App\Models\PosTerminal;
use App\Support\Filament\BranchAuthScope;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PosTerminalsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('code')
                    ->label('Código del punto')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Código copiado')
                    ->weight('medium')
                    ->icon(Heroicon::Hashtag)
                    ->iconColor('gray'),
                TextColumn::make('bank_code')
                    ->label('Banco')
                    ->state(fn (PosTerminal $record): string => $record->bankLabel())
                    ->searchable(query: function (Builder $query, string $search): void {
                        $needle = mb_strtolower($search);
                        $matchingCodes = [];

                        foreach (VenezuelanPagoMovilBank::cases() as $bank) {
                            if (str_contains(mb_strtolower($bank->optionLabel()), $needle)) {
                                $matchingCodes[] = $bank->value;
                            }
                        }

                        $query->where(function (Builder $bankQuery) use ($search, $matchingCodes): void {
                            $bankQuery->where('bank_code', 'like', "%{$search}%");

                            if ($matchingCodes !== []) {
                                $bankQuery->orWhereIn('bank_code', $matchingCodes);
                            }
                        });
                    })
                    ->sortable()
                    ->icon(Heroicon::BuildingLibrary)
                    ->iconColor('gray'),
                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->searchable()
                    ->sortable()
                    ->icon(Heroicon::BuildingStorefront)
                    ->iconColor('gray')
                    ->placeholder('—'),
                IconColumn::make('is_active')
                    ->label('Activo')
                    ->boolean()
                    ->trueIcon(Heroicon::CheckCircle)
                    ->falseIcon(Heroicon::XCircle)
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->alignCenter(),
                TextColumn::make('created_at')
                    ->label('Alta')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->icon(Heroicon::CalendarDays)
                    ->iconColor('gray'),
                TextColumn::make('updated_at')
                    ->label('Última edición')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->icon(Heroicon::Clock)
                    ->iconColor('gray'),
            ])
            ->defaultSort('code')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->persistFiltersInSession()
            ->deferFilters(false)
            ->filters([
                SelectFilter::make('branch_id')
                    ->label('Sucursal')
                    ->options(fn (): array => Branch::query()
                        ->where('is_active', true)
                        ->tap(fn ($query) => BranchAuthScope::applyToBranchFormSelect($query))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload(),
                SelectFilter::make('bank_code')
                    ->label('Banco')
                    ->options(VenezuelanPagoMovilBank::optionsForSelect())
                    ->searchable(),
                TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->placeholder('Todos')
                    ->trueLabel('Activos')
                    ->falseLabel('Inactivos'),
            ])
            ->emptyStateHeading('No hay puntos de venta')
            ->emptyStateDescription('Cree un punto de venta con su código, banco y sucursal.')
            ->emptyStateIcon(Heroicon::CreditCard)
            ->recordUrl(fn (PosTerminal $record): string => PosTerminalResource::getUrl('view', ['record' => $record], isAbsolute: false))
            ->recordAction('view')
            ->recordActions([
                ViewAction::make()
                    ->label('Ver')
                    ->icon(Heroicon::Eye),
                EditAction::make()
                    ->label('Editar')
                    ->icon(Heroicon::PencilSquare),
            ])
            ->recordActionsColumnLabel('Acciones')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionados'),
                ]),
            ]);
    }
}
