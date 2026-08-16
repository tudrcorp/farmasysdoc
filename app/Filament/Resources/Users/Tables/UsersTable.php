<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Resources\Branches\BranchResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\Branch;
use App\Models\Rol;
use App\Models\User;
use App\Support\Filament\BranchAuthScope;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\ToggleButtons;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query): Builder => $query->with('branch'))
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->description(fn (User $record): string => $record->email)
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->wrap()
                    ->lineClamp(2)
                    ->tooltip(fn (User $record): string => $record->name)
                    ->icon(Heroicon::User)
                    ->iconColor('gray'),
                TextColumn::make('email')
                    ->label('Correo')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->copyMessage('Correo copiado')
                    ->icon(Heroicon::Envelope)
                    ->iconColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('branch.name')
                    ->label('Sucursal')
                    ->description(fn (User $record): ?string => filled($record->branch?->code)
                        ? 'Código: '.$record->branch->code
                        : null)
                    ->searchable(query: function (Builder $query, string $search): void {
                        $query->whereHas('branch', function (Builder $q) use ($search): void {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhere('code', 'like', "%{$search}%");
                        });
                    })
                    ->sortable()
                    ->placeholder('—')
                    ->icon(Heroicon::BuildingOffice2)
                    ->iconColor('gray')
                    ->url(fn (User $record): ?string => $record->branch_id
                        ? BranchResource::getUrl('view', ['record' => $record->branch_id], isAbsolute: false)
                        : null)
                    ->openUrlInNewTab(false)
                    ->limit(32)
                    ->tooltip(fn (User $record): string => $record->branch?->name ?? 'Sin sucursal'),
                TextColumn::make('roles')
                    ->label('Roles')
                    ->formatStateUsing(function (mixed $state): string {
                        $roles = self::normalizeRolesColumnState($state);
                        if ($roles === []) {
                            return '—';
                        }

                        return collect($roles)
                            ->map(fn (mixed $role): string => mb_strtoupper(trim((string) $role)))
                            ->filter(fn (string $role): bool => filled($role))
                            ->unique()
                            ->sort()
                            ->values()
                            ->join(' · ');
                    })
                    ->searchable(query: function (Builder $query, string $search): void {
                        $needle = addcslashes($search, '%_\\');
                        $query->where('roles', 'like', '%"'.$needle.'"%');
                    })
                    ->icon(Heroicon::ShieldCheck)
                    ->iconColor('gray'),
                TextColumn::make('delivery_identity_document')
                    ->label('Cédula (entrega)')
                    ->icon(Heroicon::Identification)
                    ->iconColor('gray')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable()
                    ->getStateUsing(fn (User $record): ?string => $record->isDeliveryUser()
                        ? (filled($record->delivery_identity_document) ? (string) $record->delivery_identity_document : null)
                        : null)
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('delivery_mobile_phone')
                    ->label('Teléfono móvil')
                    ->icon(Heroicon::Phone)
                    ->iconColor('gray')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable()
                    ->getStateUsing(fn (User $record): ?string => filled($record->delivery_mobile_phone)
                        ? (string) $record->delivery_mobile_phone
                        : null)
                    ->toggleable(isToggledHiddenByDefault: false),
                TextColumn::make('whatsapp_phone')
                    ->label('WhatsApp')
                    ->icon(Heroicon::ChatBubbleLeftRight)
                    ->iconColor('gray')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable()
                    ->getStateUsing(fn (User $record): ?string => filled($record->whatsapp_phone)
                        ? (string) $record->whatsapp_phone
                        : null)
                    ->toggleable(isToggledHiddenByDefault: false),
                ImageColumn::make('delivery_photo_path')
                    ->label('Foto entrega')
                    ->disk('public')
                    ->circular()
                    ->height(40)
                    ->width(40)
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->placeholder('—'),
                IconColumn::make('is_email_verified')
                    ->label('Correo verif.')
                    ->boolean()
                    ->getStateUsing(fn (User $record): bool => filled($record->email_verified_at))
                    ->trueIcon(Heroicon::CheckCircle)
                    ->falseIcon(Heroicon::XCircle)
                    ->trueColor('success')
                    ->falseColor('warning')
                    ->alignCenter()
                    ->tooltip(fn (User $record): string => filled($record->email_verified_at)
                        ? 'Correo verificado el '.$record->email_verified_at->format('d/m/Y H:i')
                        : 'Correo pendiente de verificación'),
                IconColumn::make('has_two_factor')
                    ->label('2FA')
                    ->boolean()
                    ->getStateUsing(fn (User $record): bool => filled($record->two_factor_confirmed_at))
                    ->trueIcon(Heroicon::LockClosed)
                    ->falseIcon(Heroicon::LockOpen)
                    ->trueColor('success')
                    ->falseColor('gray')
                    ->alignCenter()
                    ->tooltip(fn (User $record): string => filled($record->two_factor_confirmed_at)
                        ? '2FA activa desde el '.Carbon::parse($record->two_factor_confirmed_at)->format('d/m/Y H:i')
                        : '2FA no configurada'),
                TextColumn::make('email_verified_at')
                    ->label('Verificado el')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('two_factor_confirmed_at')
                    ->label('2FA confirmada')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name', 'asc')
            ->striped()
            ->paginated([10, 25, 50, 100])
            ->defaultPaginationPageOption(25)
            ->persistFiltersInSession()
            ->deferFilters(false)
            ->filtersFormColumns(1)
            ->filtersLayout(FiltersLayout::AboveContent)
            ->emptyStateHeading('Sin usuarios registrados')
            ->emptyStateDescription('Crea un usuario para asignar acceso al panel y vincularlo a una sucursal. Usa «Crear usuario» en el encabezado.')
            ->emptyStateIcon(Heroicon::UserGroup)
            ->recordUrl(fn (User $record): string => UserResource::getUrl('view', ['record' => $record], isAbsolute: false))
            ->recordAction('view')
            ->filters([
                Filter::make('user_view')
                    ->label('Filtros')
                    ->columnSpanFull()
                    ->schema([
                        Grid::make([
                            'default' => 1,
                            'md' => 2,
                            'xl' => 4,
                        ])->schema([
                            Select::make('branch_id')
                                ->label('Sucursal')
                                ->placeholder('Todas')
                                ->multiple()
                                ->searchable()
                                ->preload()
                                ->native(false)
                                ->options(function (): array {
                                    $query = Branch::query()->where('is_active', true)->orderBy('name');
                                    $query = BranchAuthScope::applyToBranchFormSelect($query);

                                    return $query->get()
                                        ->mapWithKeys(fn (Branch $record): array => [
                                            $record->getKey() => filled($record->code)
                                                ? $record->name.' ('.$record->code.')'
                                                : $record->name,
                                        ])
                                        ->all();
                                })
                                ->extraAttributes(['class' => 'fi-hr-ios-select']),
                            Select::make('role')
                                ->label('Rol')
                                ->placeholder('Todos')
                                ->searchable()
                                ->native(false)
                                ->options(fn (): array => Rol::query()
                                    ->where('is_active', true)
                                    ->orderBy('name')
                                    ->get()
                                    ->mapWithKeys(fn (Rol $rol): array => [
                                        $rol->name => mb_strtoupper((string) $rol->name),
                                    ])
                                    ->all())
                                ->extraAttributes(['class' => 'fi-hr-ios-select']),
                            self::iosSegment(
                                'email_verified',
                                'Correo verificado',
                                [
                                    'all' => 'Todos',
                                    '1' => 'Sí',
                                    '0' => 'No',
                                ],
                            ),
                            self::iosSegment(
                                'two_factor',
                                '2FA',
                                [
                                    'all' => 'Todos',
                                    '1' => 'Sí',
                                    '0' => 'No',
                                ],
                            ),
                        ]),
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $branchIds = $data['branch_id'] ?? [];
                        if (is_array($branchIds) && $branchIds !== []) {
                            $query->whereIn('branch_id', $branchIds);
                        }

                        if (filled($data['role'] ?? null)) {
                            $query->whereJsonContains('roles', $data['role']);
                        }

                        $emailVerified = $data['email_verified'] ?? 'all';
                        if ($emailVerified === '1') {
                            $query->whereNotNull('email_verified_at');
                        } elseif ($emailVerified === '0') {
                            $query->whereNull('email_verified_at');
                        }

                        $twoFactor = $data['two_factor'] ?? 'all';
                        if ($twoFactor === '1') {
                            $query->whereNotNull('two_factor_confirmed_at');
                        } elseif ($twoFactor === '0') {
                            $query->whereNull('two_factor_confirmed_at');
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

    /**
     * La columna JSON puede llegar como array (cast Eloquent) o como string JSON desde la consulta de la tabla.
     *
     * @return list<string>
     */
    private static function normalizeRolesColumnState(mixed $state): array
    {
        if ($state === null || $state === '') {
            return [];
        }

        if (is_array($state)) {
            return array_values(array_filter($state, fn (mixed $role): bool => filled($role)));
        }

        if (is_string($state)) {
            $decoded = json_decode($state, true);
            if (is_array($decoded)) {
                return array_values(array_filter($decoded, fn (mixed $role): bool => filled($role)));
            }

            return filled($state) ? [$state] : [];
        }

        return [];
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
