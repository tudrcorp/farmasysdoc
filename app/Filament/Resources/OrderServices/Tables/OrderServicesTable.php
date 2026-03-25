<?php

namespace App\Filament\Resources\OrderServices\Tables;

use App\Filament\Resources\OrderServices\OrderServiceResource;
use App\Models\OrderService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

class OrderServicesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with(['partnerCompany', 'client', 'branch']))
            ->columns([
                TextColumn::make('service_order_number')
                    ->label('Número')
                    ->searchable()
                    ->sortable()
                    ->badge()
                    ->color('primary')
                    ->copyable()
                    ->copyMessage('Número copiado')
                    ->weight('medium')
                    ->icon(Heroicon::Hashtag)
                    ->iconColor('gray'),
                TextColumn::make('partnerCompany.legal_name')
                    ->label('Compañía aliada')
                    ->description(fn (OrderService $record): ?string => filled($record->partnerCompany?->code)
                        ? 'Código: '.$record->partnerCompany->code
                        : null)
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('partnerCompany', function (Builder $q) use ($search): void {
                            $q->where('legal_name', 'like', "%{$search}%")
                                ->orWhere('trade_name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                    })
                    ->sortable()
                    ->placeholder('—')
                    ->wrap()
                    ->lineClamp(2)
                    ->tooltip(fn (OrderService $record): string => (string) ($record->partnerCompany?->legal_name ?? ''))
                    ->icon(Heroicon::BuildingOffice2)
                    ->iconColor('gray'),
                TextColumn::make('client.name')
                    ->label('Cliente')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable()
                    ->icon(Heroicon::User)
                    ->iconColor('gray'),
                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->icon(Heroicon::MapPin)
                    ->iconColor('gray'),
                TextColumn::make('items')
                    ->label('Medicamentos')
                    ->formatStateUsing(function (?array $state): string {
                        $rows = self::medicationRowsForDisplay($state);

                        if ($rows->isEmpty()) {
                            return '—';
                        }

                        $count = $rows->count();

                        return $count === 1 ? '1 ítem' : "{$count} ítems";
                    })
                    ->badge()
                    ->color(fn (?array $state): string => self::medicationRowsForDisplay($state)->isNotEmpty() ? 'success' : 'gray')
                    ->tooltip(function (?array $state): ?string {
                        $lines = self::medicationRowsForDisplay($state)->map(function (array $row): string {
                            $name = $row['name'];
                            $ind = $row['indicacion'];

                            return $ind !== '' ? "{$name} — {$ind}" : $name;
                        });

                        return $lines->isEmpty() ? null : $lines->implode("\n");
                    })
                    ->alignCenter(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (?string $state): string => self::statusColor($state))
                    ->formatStateUsing(fn (?string $state): string => self::formatStatusLabel($state))
                    ->sortable(),
                TextColumn::make('priority')
                    ->label('Prioridad')
                    ->badge()
                    ->color(fn (?string $state): string => self::priorityColor($state))
                    ->formatStateUsing(fn (?string $state): string => self::formatPriorityLabel($state))
                    ->sortable(),
                TextColumn::make('service_type')
                    ->label('Tipo')
                    ->searchable()
                    ->sortable()
                    ->placeholder('—')
                    ->limit(24)
                    ->tooltip(fn (OrderService $record): ?string => $record->service_type)
                    ->icon(Heroicon::Squares2x2)
                    ->iconColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('patient_name')
                    ->label('Paciente')
                    ->searchable()
                    ->placeholder('—')
                    ->limit(28)
                    ->tooltip(fn (OrderService $record): ?string => $record->patient_name)
                    ->toggleable(),
                TextColumn::make('patient_email')
                    ->label('Correo paciente')
                    ->searchable()
                    ->placeholder('—')
                    ->limit(28)
                    ->icon(Heroicon::Envelope)
                    ->iconColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('ordered_at')
                    ->label('Emitida')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—'),
                TextColumn::make('scheduled_at')
                    ->label('Programada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('started_at')
                    ->label('Inicio')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('completed_at')
                    ->label('Cierre')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('total')
                    ->label('Total')
                    ->money('COP')
                    ->sortable()
                    ->alignEnd(),
                TextColumn::make('authorization_reference')
                    ->label('Ref. autorización')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->limit(16)
                    ->tooltip(fn (OrderService $record): ?string => $record->authorization_reference),
                TextColumn::make('external_reference')
                    ->label('Ref. externa')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_by')
                    ->label('Creado por')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_by')
                    ->label('Actualizado por')
                    ->searchable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Registro')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('ordered_at', 'desc')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->persistFiltersInSession()
            ->deferFilters(false)
            ->filtersFormColumns(2)
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->emptyStateHeading('Sin órdenes de servicio')
            ->emptyStateDescription('Crea una orden para vincular medicamentos, cliente y compañía aliada.')
            ->emptyStateIcon(Heroicon::ClipboardDocumentList)
            ->recordUrl(fn (OrderService $record): string => OrderServiceResource::getUrl('view', ['record' => $record], isAbsolute: false))
            ->recordAction('view')
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options([
                        'borrador' => 'Borrador',
                        'aprobada' => 'Aprobada',
                        'en-proceso' => 'En proceso',
                        'finalizada' => 'Finalizada',
                        'cancelada' => 'Cancelada',
                    ]),
                SelectFilter::make('priority')
                    ->label('Prioridad')
                    ->options([
                        'baja' => 'Baja',
                        'media' => 'Media',
                        'alta' => 'Alta',
                        'urgente' => 'Urgente',
                    ]),
                TernaryFilter::make('has_medications')
                    ->label('Medicamentos')
                    ->placeholder('Todas')
                    ->trueLabel('Con medicamentos listados')
                    ->falseLabel('Sin medicamentos listados')
                    ->queries(
                        true: fn (Builder $query): Builder => $query->whereJsonLength('items', '>', 0),
                        false: fn (Builder $query): Builder => $query->where(function (Builder $q): void {
                            $q->whereNull('items')
                                ->orWhereJsonLength('items', '=', 0);
                        }),
                        blank: fn (Builder $query): Builder => $query,
                    ),
            ])
            ->recordActions([
                ViewAction::make()
                    ->label('Ver ficha')
                    ->icon(Heroicon::Eye),
                EditAction::make()
                    ->label('Editar')
                    ->icon(Heroicon::PencilSquare),
            ])
            ->recordActionsColumnLabel('Acciones')
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionadas'),
                ]),
            ]);
    }

    /**
     * Filas de medicamento para contar y tooltip: solo cuentan entradas con nombre
     * (clave `name` no vacía). Evita contar mal cuando `items` es un solo objeto
     * asociativo `{ position, name, indicacion }` (count nativo daría 3 claves).
     *
     * @return Collection<int, array{name: string, indicacion: string}>
     */
    private static function medicationRowsForDisplay(?array $state): Collection
    {
        if ($state === null || $state === []) {
            return collect();
        }

        if (! array_is_list($state) && array_key_exists('name', $state)) {
            $name = trim((string) ($state['name'] ?? ''));

            if ($name === '') {
                return collect();
            }

            return collect([[
                'name' => $name,
                'indicacion' => trim((string) ($state['indicacion'] ?? '')),
            ]]);
        }

        return collect($state)
            ->map(function (mixed $row): ?array {
                if (is_string($row)) {
                    $name = trim($row);

                    return $name === '' ? null : [
                        'name' => $name,
                        'indicacion' => '',
                    ];
                }

                if (! is_array($row) || ! array_key_exists('name', $row)) {
                    return null;
                }

                $name = trim((string) ($row['name'] ?? ''));

                if ($name === '') {
                    return null;
                }

                return [
                    'name' => $name,
                    'indicacion' => trim((string) ($row['indicacion'] ?? '')),
                ];
            })
            ->filter()
            ->values();
    }

    private static function statusColor(?string $status): string
    {
        return match (strtolower((string) $status)) {
            'borrador' => 'gray',
            'aprobada' => 'info',
            'en-proceso', 'en proceso' => 'warning',
            'finalizada' => 'success',
            'cancelada' => 'danger',
            default => 'gray',
        };
    }

    private static function formatStatusLabel(?string $status): string
    {
        if (blank($status)) {
            return '—';
        }

        return match (strtolower($status)) {
            'borrador' => 'Borrador',
            'aprobada' => 'Aprobada',
            'en-proceso', 'en proceso' => 'En proceso',
            'finalizada' => 'Finalizada',
            'cancelada' => 'Cancelada',
            default => (string) $status,
        };
    }

    private static function priorityColor(?string $priority): string
    {
        return match (strtolower((string) $priority)) {
            'baja' => 'gray',
            'media' => 'info',
            'alta' => 'warning',
            'urgente' => 'danger',
            default => 'gray',
        };
    }

    private static function formatPriorityLabel(?string $priority): string
    {
        if (blank($priority)) {
            return '—';
        }

        return match (strtolower($priority)) {
            'baja' => 'Baja',
            'media' => 'Media',
            'alta' => 'Alta',
            'urgente' => 'Urgente',
            default => (string) $priority,
        };
    }
}
