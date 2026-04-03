<?php

namespace App\Filament\Resources\ProductTransfers\Tables;

use App\Filament\Resources\ProductTransfers\ProductTransferResource;
use App\Models\ProductTransfer;
use App\Models\User;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ProductTransfersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with([
                'items',
                'fromBranch',
                'toBranch',
            ]))
            ->columns([
                TextColumn::make('code')
                    ->label('Código')
                    ->badge()
                    ->color('primary')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Código copiado')
                    ->weight('medium')
                    ->icon(Heroicon::Hashtag)
                    ->iconColor('gray')
                    ->placeholder('—'),
                TextColumn::make('items_count')
                    ->counts('items')
                    ->label('Líneas')
                    ->sortable()
                    ->alignEnd()
                    ->icon(Heroicon::Cube)
                    ->iconColor('gray'),
                TextColumn::make('fromBranch.name')
                    ->label('Origen')
                    ->description(fn (ProductTransfer $record): ?string => filled($record->fromBranch?->code)
                        ? 'Código: '.$record->fromBranch->code
                        : null)
                    ->searchable(query: function (Builder $query, string $search): void {
                        $query->whereHas('fromBranch', function (Builder $q) use ($search): void {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                    })
                    ->sortable()
                    ->icon(Heroicon::BuildingStorefront)
                    ->iconColor('gray')
                    ->placeholder('—'),
                TextColumn::make('toBranch.name')
                    ->label('Destino')
                    ->description(fn (ProductTransfer $record): ?string => filled($record->toBranch?->code)
                        ? 'Código: '.$record->toBranch->code
                        : null)
                    ->searchable(query: function (Builder $query, string $search): void {
                        $query->whereHas('toBranch', function (Builder $q) use ($search): void {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                    })
                    ->sortable()
                    ->icon(Heroicon::MapPin)
                    ->iconColor('gray')
                    ->placeholder('—'),
                TextColumn::make('total_transfer_cost')
                    ->label('Costo traslado')
                    ->money('USD')
                    ->sortable()
                    ->alignEnd()
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::formatStatusLabel($state))
                    ->color(fn (?string $state): string => self::statusBadgeColor($state))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('transfer_type')
                    ->label('Tipo')
                    ->badge()
                    ->color('gray')
                    ->formatStateUsing(fn (?string $state): string => self::formatTransferTypeLabel($state))
                    ->searchable()
                    ->sortable()
                    ->icon(Heroicon::ArrowsRightLeft)
                    ->iconColor('gray'),
                TextColumn::make('created_by')
                    ->label('Creado por')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—')
                    ->icon(Heroicon::UserCircle)
                    ->iconColor('gray'),
                TextColumn::make('completed_by')
                    ->label('Completado por')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—')
                    ->icon(Heroicon::CheckCircle)
                    ->iconColor('gray'),
                TextColumn::make('completed_at')
                    ->label('Completado el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
                TextColumn::make('updated_by')
                    ->label('Actualizado por')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—')
                    ->icon(Heroicon::ArrowPath)
                    ->iconColor('gray'),
                TextColumn::make('created_at')
                    ->label('Registro')
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
            ->defaultSort('created_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->persistFiltersInSession()
            ->deferFilters(false)
            ->emptyStateHeading('No hay traslados')
            ->emptyStateDescription('Registre un traslado entre sucursales para verlo en esta lista.')
            ->emptyStateIcon(Heroicon::ArrowPath)
            ->recordUrl(fn (ProductTransfer $record): string => ProductTransferResource::getUrl('view', ['record' => $record], isAbsolute: false))
            ->recordAction('view')
            ->recordActions([
                ViewAction::make()
                    ->label('Ver traslado')
                    ->icon(Heroicon::Eye),
                ProductTransferResource::markCompletedAction(),
                EditAction::make()
                    ->label('Editar')
                    ->icon(Heroicon::PencilSquare)
                    ->visible(fn (): bool => auth()->user() instanceof User && auth()->user()->isAdministrator()),
            ])
            ->recordActionsColumnLabel('Acciones')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionados')
                        ->visible(fn (): bool => auth()->user() instanceof User && auth()->user()->isAdministrator()),
                ]),
            ]);
    }

    private static function formatStatusLabel(?string $state): string
    {
        if (blank($state)) {
            return '—';
        }

        $key = strtolower(trim($state));

        return match ($key) {
            'pending', 'pendiente' => 'Pendiente',
            'in_progress', 'en_proceso', 'en proceso' => 'En proceso',
            'completed', 'completado', 'completada' => 'Completado',
            'cancelled', 'cancelado', 'cancelada' => 'Cancelado',
            default => (string) $state,
        };
    }

    private static function statusBadgeColor(?string $state): string
    {
        if (blank($state)) {
            return 'gray';
        }

        $key = strtolower(trim($state));

        return match ($key) {
            'pending', 'pendiente' => 'warning',
            'in_progress', 'en_proceso' => 'info',
            'completed', 'completado', 'completada' => 'success',
            'cancelled', 'cancelado', 'cancelada' => 'danger',
            default => 'gray',
        };
    }

    private static function formatTransferTypeLabel(?string $state): string
    {
        if (blank($state)) {
            return '—';
        }

        $key = strtolower(trim(str_replace([' ', '-'], '_', $state)));

        return match ($key) {
            'internal', 'interno' => 'Interno',
            'external', 'externo' => 'Externo',
            'adjustment', 'ajuste' => 'Ajuste',
            default => (string) $state,
        };
    }
}
