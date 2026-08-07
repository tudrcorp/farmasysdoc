<?php

namespace App\Filament\Resources\ClientDiscounts\Groups\Tables;

use App\Filament\Resources\ClientDiscounts\Groups\ClientDiscountGroupResource;
use App\Models\ClientDiscountGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClientDiscountGroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount('clients'))
            ->columns([
                TextColumn::make('name')
                    ->label('Grupo')
                    ->description(fn (ClientDiscountGroup $record): string => self::clientsSummary($record))
                    ->searchable()
                    ->sortable()
                    ->weight(FontWeight::Medium)
                    ->wrap()
                    ->lineClamp(2)
                    ->tooltip(fn (ClientDiscountGroup $record): string => $record->name)
                    ->icon(Heroicon::UserGroup)
                    ->iconColor('primary')
                    ->placeholder('—'),
                TextColumn::make('discount_percent')
                    ->label('Descuento')
                    ->badge()
                    ->color('success')
                    ->sortable()
                    ->alignCenter()
                    ->icon(Heroicon::ReceiptPercent)
                    ->formatStateUsing(fn ($state): string => self::formatPercent($state).'%')
                    ->tooltip('Porcentaje aplicado a todos los clientes del grupo en caja'),
                TextColumn::make('clients_count')
                    ->label('Clientes')
                    ->sortable()
                    ->alignCenter()
                    ->badge()
                    ->color(fn (ClientDiscountGroup $record): string => ($record->clients_count ?? 0) > 0 ? 'info' : 'gray')
                    ->icon(Heroicon::Users)
                    ->formatStateUsing(fn ($state): string => (string) ((int) $state))
                    ->tooltip('Cantidad de clientes asociados al grupo'),
                TextColumn::make('is_active')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (bool|int|string|null $state): string => filter_var($state, FILTER_VALIDATE_BOOLEAN) ? 'Activo' : 'Inactivo')
                    ->color(fn (bool|int|string|null $state): string => filter_var($state, FILTER_VALIDATE_BOOLEAN) ? 'success' : 'gray')
                    ->icon(fn (bool|int|string|null $state): Heroicon => filter_var($state, FILTER_VALIDATE_BOOLEAN)
                        ? Heroicon::CheckCircle
                        : Heroicon::PauseCircle)
                    ->sortable(),
                TextColumn::make('notes')
                    ->label('Notas')
                    ->limit(40)
                    ->tooltip(fn (ClientDiscountGroup $record): ?string => filled($record->notes) ? (string) $record->notes : null)
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->wrap(),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->since()
                    ->sortable()
                    ->description(fn (ClientDiscountGroup $record): string => $record->updated_at?->format('d/m/Y H:i') ?? '—')
                    ->icon(Heroicon::Clock)
                    ->iconColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Estado')
                    ->placeholder('Todos')
                    ->trueLabel('Solo activos')
                    ->falseLabel('Solo inactivos')
                    ->native(false),
                SelectFilter::make('has_clients')
                    ->label('Clientes asociados')
                    ->options([
                        'yes' => 'Con clientes',
                        'no' => 'Sin clientes',
                    ])
                    ->native(false)
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'yes' => $query->has('clients'),
                            'no' => $query->doesntHave('clients'),
                            default => $query,
                        };
                    }),
                SelectFilter::make('discount_band')
                    ->label('Rango de descuento')
                    ->options([
                        'low' => 'Hasta 5%',
                        'mid' => '5% – 15%',
                        'high' => 'Más de 15%',
                    ])
                    ->native(false)
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        return match ($value) {
                            'low' => $query->where('discount_percent', '<=', 5),
                            'mid' => $query->whereBetween('discount_percent', [5.01, 15]),
                            'high' => $query->where('discount_percent', '>', 15),
                            default => $query,
                        };
                    }),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Ver')
                    ->icon(Heroicon::Eye)
                    ->color('gray'),
                EditAction::make()
                    ->label('Editar')
                    ->icon(Heroicon::PencilSquare)
                    ->color('primary'),
                DeleteAction::make()
                    ->label('Eliminar')
                    ->icon(Heroicon::Trash)
                    ->modalHeading('Eliminar grupo de descuento')
                    ->modalDescription('Los clientes del grupo quedarán sin este descuento (no se eliminan los clientes).'),
            ])
            ->recordActionsColumnLabel('Acciones')
            ->recordUrl(fn (ClientDiscountGroup $record): string => ClientDiscountGroupResource::getUrl('view', ['record' => $record], isAbsolute: false))
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionados')
                        ->modalHeading('Eliminar grupos seleccionados')
                        ->modalDescription('Los clientes asociados quedarán sin el descuento de grupo.'),
                ]),
            ])
            ->defaultSort('name')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->persistFiltersInSession()
            ->emptyStateHeading('Sin grupos de descuento')
            ->emptyStateDescription('Crea un grupo, define el porcentaje y asocia clientes. El descuento se aplica a todos en caja.')
            ->emptyStateIcon(Heroicon::UserGroup);
    }

    private static function clientsSummary(ClientDiscountGroup $record): string
    {
        $count = (int) ($record->clients_count ?? 0);

        return match (true) {
            $count === 0 => 'Sin clientes asociados',
            $count === 1 => '1 cliente asociado',
            default => $count.' clientes asociados',
        };
    }

    private static function formatPercent(mixed $state): string
    {
        if ($state === null || $state === '' || ! is_numeric($state)) {
            return '0';
        }

        return rtrim(rtrim(number_format((float) $state, 2, ',', '.'), '0'), ',');
    }
}
