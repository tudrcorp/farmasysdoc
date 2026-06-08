<?php

namespace App\Filament\Resources\Rols\Tables;

use App\Filament\Resources\Rols\RolResource;
use App\Models\Rol;
use App\Models\User;
use App\Support\Filament\FarmaadminMenuAccessCatalog;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Enums\FiltersLayout;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class RolsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Rol')
                    ->formatStateUsing(fn (string $state): string => mb_strtoupper($state))
                    ->description(fn (Rol $record): string => filled($record->description)
                        ? Str::limit((string) $record->description, 80)
                        : 'Sin descripción registrada')
                    ->searchable()
                    ->sortable()
                    ->weight('medium')
                    ->wrap()
                    ->lineClamp(2)
                    ->tooltip(fn (Rol $record): string => mb_strtoupper((string) $record->name))
                    ->icon(Heroicon::ShieldCheck)
                    ->iconColor(fn (Rol $record): string => self::isAdministratorRole($record) ? 'primary' : 'gray')
                    ->badge()
                    ->color(fn (Rol $record): string => self::isAdministratorRole($record) ? 'primary' : 'gray'),
                TextColumn::make('permission_scope')
                    ->label('Permisos de menú')
                    ->state(fn (Rol $record): string => self::permissionScopeLabel($record))
                    ->description(fn (Rol $record): string => self::permissionGroupsDescription($record))
                    ->badge()
                    ->color(fn (Rol $record): string => self::permissionScopeColor($record))
                    ->icon(Heroicon::SquaresPlus)
                    ->iconColor('gray')
                    ->wrap()
                    ->lineClamp(2)
                    ->tooltip(fn (Rol $record): string => self::permissionGroupsDescription($record)),
                TextColumn::make('users_count')
                    ->label('Usuarios')
                    ->state(fn (Rol $record): int => User::query()
                        ->whereJsonContains('roles', $record->name)
                        ->count())
                    ->alignEnd()
                    ->sortable(false)
                    ->badge()
                    ->color(fn (int $state): string => match (true) {
                        $state === 0 => 'gray',
                        $state === 1 => 'info',
                        default => 'success',
                    })
                    ->icon(Heroicon::UserGroup)
                    ->tooltip('Usuarios con este rol asignado'),
                IconColumn::make('is_active')
                    ->label('Estado')
                    ->boolean()
                    ->trueIcon(Heroicon::CheckCircle)
                    ->falseIcon(Heroicon::PauseCircle)
                    ->trueColor('success')
                    ->falseColor('danger')
                    ->alignCenter()
                    ->sortable()
                    ->tooltip(fn (Rol $record): string => $record->is_active
                        ? 'Rol activo: disponible al crear o editar usuarios'
                        : 'Rol inactivo: no aparece en la asignación de usuarios'),
                TextColumn::make('description')
                    ->label('Descripción completa')
                    ->placeholder('—')
                    ->wrap()
                    ->lineClamp(3)
                    ->searchable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->sinceTooltip()
                    ->icon(Heroicon::CalendarDays)
                    ->iconColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->sinceTooltip()
                    ->icon(Heroicon::Clock)
                    ->iconColor('gray')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->striped()
            ->paginated([10, 25, 50])
            ->defaultPaginationPageOption(25)
            ->persistFiltersInSession()
            ->deferFilters(false)
            ->filtersFormColumns(2)
            ->filtersLayout(FiltersLayout::AboveContentCollapsible)
            ->emptyStateHeading('Sin roles configurados')
            ->emptyStateDescription('Cree un rol para definir qué módulos del panel puede ver cada perfil de usuario.')
            ->emptyStateIcon(Heroicon::ShieldCheck)
            ->recordUrl(fn (Rol $record): string => RolResource::getUrl('view', ['record' => $record], isAbsolute: false))
            ->recordAction('view')
            ->filters([
                TernaryFilter::make('is_active')
                    ->label('Estado del rol')
                    ->placeholder('Todos')
                    ->trueLabel('Solo activos')
                    ->falseLabel('Solo inactivos'),
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

    private static function isAdministratorRole(Rol $record): bool
    {
        return mb_strtoupper((string) $record->name) === 'ADMINISTRADOR';
    }

    private static function menuItemsCount(Rol $record): int
    {
        if ($record->allowed_menu_items === null) {
            return count(User::defaultAllowedMenuItems());
        }

        return is_array($record->allowed_menu_items)
            ? count($record->allowed_menu_items)
            : 0;
    }

    private static function hasFullAccess(Rol $record): bool
    {
        if ($record->allowed_menu_items === null) {
            return true;
        }

        return count($record->allowed_menu_items) >= count(FarmaadminMenuAccessCatalog::allKeys());
    }

    private static function permissionScopeLabel(Rol $record): string
    {
        if (self::hasFullAccess($record)) {
            return 'Acceso completo';
        }

        $count = self::menuItemsCount($record);

        if ($count === 0) {
            return 'Sin módulos';
        }

        return $count.' módulos';
    }

    private static function permissionScopeColor(Rol $record): string
    {
        if (self::hasFullAccess($record)) {
            return 'success';
        }

        $count = self::menuItemsCount($record);

        if ($count === 0) {
            return 'danger';
        }

        if ($count < 8) {
            return 'warning';
        }

        return 'info';
    }

    private static function permissionGroupsDescription(Rol $record): string
    {
        if (self::hasFullAccess($record)) {
            return 'Todos los módulos del panel Farmaadmin';
        }

        $allowed = $record->allowed_menu_items;

        if (! is_array($allowed) || $allowed === []) {
            return 'Ningún módulo asignado';
        }

        $groups = [];

        foreach ($allowed as $key) {
            $group = FarmaadminMenuAccessCatalog::items()[$key]['group'] ?? 'Otros';
            $groups[$group] = ($groups[$group] ?? 0) + 1;
        }

        return collect($groups)
            ->sortKeys()
            ->map(fn (int $count, string $group): string => $group.' ('.$count.')')
            ->implode(' · ');
    }
}
