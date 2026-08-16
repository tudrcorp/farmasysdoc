<?php

namespace App\Filament\Resources\Clients\Tables;

use App\Filament\Resources\Clients\ClientResource;
use App\Models\Client;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ClientsTable
{
    /**
     * @return array<string, string>
     */
    private static function documentTypeOptions(): array
    {
        return [
            'CC' => 'Cédula de Identidad',
            'CE' => 'Cédula Extranjero',
            'RIF' => 'RIF',
            'NIT' => 'NIT',
            'PAS' => 'Pasaporte',
        ];
    }

    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->withCount(['sales', 'orders']))
            ->columns([
                TextColumn::make('name')
                    ->label('Cliente')
                    ->description(fn (Client $record): ?string => self::formatNameSubtitle($record))
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->lineClamp(2)
                    ->wrap()
                    ->tooltip(fn (Client $record): string => $record->name)
                    ->icon(Heroicon::User)
                    ->iconColor('gray')
                    ->placeholder('—'),
                TextColumn::make('document_number')
                    ->label('Nº documento')
                    ->badge()
                    ->color('gray')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Documento copiado')
                    ->formatStateUsing(fn (?string $state, Client $record): string => filled($state)
                        ? (string) $state
                        : '—')
                    ->description(fn (Client $record): ?string => self::documentTypeLabel($record->document_type))
                    ->icon(Heroicon::Identification)
                    ->iconColor('gray')
                    ->placeholder('—'),
                TextColumn::make('email')
                    ->label('Correo')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Correo copiado')
                    ->limit(28)
                    ->tooltip(fn (Client $record): string => (string) ($record->email ?? ''))
                    ->icon(Heroicon::Envelope)
                    ->iconColor('gray')
                    ->placeholder('—'),
                TextColumn::make('phone')
                    ->label('Teléfono')
                    ->searchable()
                    ->copyable()
                    ->copyMessage('Teléfono copiado')
                    ->icon(Heroicon::Phone)
                    ->iconColor('gray')
                    ->placeholder('—'),
                TextColumn::make('location')
                    ->label('Ubicación')
                    ->state(fn (Client $record): string => self::formatLocationLine($record))
                    ->searchable(query: function (Builder $query, string $search): void {
                        $like = "%{$search}%";
                        $query->where(function (Builder $q) use ($like): void {
                            $q->where('city', 'like', $like)
                                ->orWhere('state', 'like', $like)
                                ->orWhere('country', 'like', $like)
                                ->orWhere('address', 'like', $like);
                        });
                    })
                    ->limit(40)
                    ->tooltip(fn (Client $record): string => self::formatLocationLong($record))
                    ->icon(Heroicon::MapPin)
                    ->iconColor('gray')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('sales_count')
                    ->label('Ventas')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->icon(Heroicon::ShoppingBag)
                    ->iconColor('gray')
                    ->tooltip('Ventas registradas con este cliente'),
                TextColumn::make('orders_count')
                    ->label('Pedidos')
                    ->numeric()
                    ->sortable()
                    ->alignCenter()
                    ->icon(Heroicon::ClipboardDocumentList)
                    ->iconColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->tooltip('Pedidos asociados'),
                TextColumn::make('customer_discount')
                    ->label('Dto. %')
                    ->sortable()
                    ->alignEnd()
                    ->icon(Heroicon::ReceiptPercent)
                    ->iconColor('gray')
                    ->formatStateUsing(function ($state): string {
                        if ($state === null || $state === '') {
                            return '0';
                        }

                        if (! is_numeric($state)) {
                            return '—';
                        }

                        return rtrim(rtrim(number_format((float) $state, 2, ',', '.'), '0'), ',');
                    })
                    ->tooltip('Descuento comercial del cliente (porcentaje)')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('Estado')
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => self::formatStatusLabel($state))
                    ->color(fn (?string $state): string => self::statusBadgeColor($state))
                    ->searchable()
                    ->sortable(),
                TextColumn::make('address')
                    ->label('Dirección')
                    ->limit(36)
                    ->tooltip(fn (Client $record): ?string => filled($record->address) ? (string) $record->address : null)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
                TextColumn::make('city')
                    ->label('Ciudad')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
                TextColumn::make('state')
                    ->label('Departamento / estado')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
                TextColumn::make('country')
                    ->label('País')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
                TextColumn::make('created_by')
                    ->label('Creado por')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
                TextColumn::make('updated_by')
                    ->label('Actualizado por')
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
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
            ->defaultSort('name')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->persistFiltersInSession()
            ->deferFilters(false)
            ->filtersFormColumns(1)
            ->filtersLayout(FiltersLayout::AboveContent)
            ->emptyStateHeading('No hay clientes')
            ->emptyStateDescription('Crea el primero para usarlo en ventas, pedidos y convenios.')
            ->emptyStateIcon(Heroicon::UserGroup)
            ->recordUrl(fn (Client $record): string => ClientResource::getUrl('view', ['record' => $record], isAbsolute: false))
            ->recordAction('view')
            ->filters([
                Filter::make('client_view')
                    ->label('Filtros')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                            'xl' => 3,
                        ])->schema([
                            self::iosSegment(
                                'status',
                                'Estado',
                                [
                                    'all' => 'Todos',
                                    'active' => 'Activo',
                                    'inactive' => 'Inactivo',
                                    'blocked' => 'Bloqueado',
                                ],
                            ),
                            Select::make('document_type')
                                ->label('Tipo de documento')
                                ->placeholder('Todos')
                                ->native(false)
                                ->options(self::documentTypeOptions())
                                ->extraAttributes(['class' => 'fi-hr-ios-select']),
                            self::iosSegment(
                                'has_sales',
                                'Con ventas',
                                [
                                    'all' => 'Todos',
                                    '1' => 'Sí',
                                    '0' => 'No',
                                ],
                            ),
                        ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $status = $data['status'] ?? 'all';
                        if (filled($status) && $status !== 'all') {
                            $query->where('status', $status);
                        }

                        $documentType = $data['document_type'] ?? null;
                        if (filled($documentType)) {
                            $query->where('document_type', $documentType);
                        }

                        $hasSales = $data['has_sales'] ?? 'all';
                        if ($hasSales === '1') {
                            $query->has('sales');
                        } elseif ($hasSales === '0') {
                            $query->doesntHave('sales');
                        }

                        return $query;
                    }),
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
                        ->label('Eliminar seleccionados'),
                ]),
            ]);
    }

    private static function documentTypeLabel(?string $code): string
    {
        if (blank($code)) {
            return '—';
        }

        return self::documentTypeOptions()[$code] ?? (string) $code;
    }

    /**
     * Subtítulo bajo el nombre: contacto principal (sin repetir la columna de documento).
     */
    private static function formatNameSubtitle(Client $client): ?string
    {
        $email = trim((string) ($client->email ?? ''));
        if ($email !== '') {
            return $email;
        }

        $phone = trim((string) ($client->phone ?? ''));

        return $phone !== '' ? $phone : null;
    }

    private static function formatStatusLabel(?string $status): string
    {
        return match ($status) {
            'active' => 'Activo',
            'inactive' => 'Inactivo',
            'blocked' => 'Bloqueado',
            default => filled($status) ? (string) $status : '—',
        };
    }

    private static function statusBadgeColor(?string $status): string
    {
        return match ($status) {
            'active' => 'success',
            'inactive' => 'gray',
            'blocked' => 'danger',
            default => 'gray',
        };
    }

    private static function formatLocationLine(Client $client): string
    {
        $parts = array_filter([
            $client->city,
            $client->state,
            $client->country,
        ], fn (mixed $v): bool => filled($v));

        return $parts !== [] ? implode(' · ', $parts) : '—';
    }

    private static function formatLocationLong(Client $client): string
    {
        $line = self::formatLocationLine($client);
        $addr = trim((string) ($client->address ?? ''));

        if ($addr !== '') {
            return $line !== '—' ? $addr.' — '.$line : $addr;
        }

        return $line;
    }

    /**
     * @param  array<string, string>  $options
     */
    private static function iosSegment(string $name, string $label, array $options): ToggleButtons
    {
        return ToggleButtons::make($name)
            ->label($label)
            ->options($options)
            ->grouped()
            ->default('all')
            ->extraAttributes([
                'class' => 'fi-hr-ios-segment',
                'data-segment-count' => (string) count($options),
            ]);
    }
}
