<?php

namespace App\Filament\BusinessPartners\Resources\TeamUsers;

use App\Enums\PartnerPanelPermission;
use App\Filament\BusinessPartners\Resources\TeamUsers\Pages\ManageTeamUsers;
use App\Models\User;
use App\Services\Partners\PartnerPanelUserService;
use App\Support\Partners\PartnerPanel;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;

/**
 * «Usuarios» de dale · Aliados: el administrador del panel (designado por Farmadoc) gestiona a su
 * equipo y sus permisos. No puede tocar a otros administradores ni a sí mismo; las reglas se
 * validan en {@see PartnerPanelUserService}. Nunca se borran usuarios: se suspenden.
 */
class TeamUserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $slug = 'usuarios';

    protected static ?string $navigationLabel = 'Usuarios';

    protected static ?string $modelLabel = 'usuario';

    protected static ?string $pluralModelLabel = 'usuarios';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|BackedEnum|null $activeNavigationIcon = Heroicon::UserGroup;

    protected static ?int $navigationSort = 10;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isGloballySearchable = false;

    public static function getNavigationGroup(): ?string
    {
        return 'Equipo';
    }

    public static function canViewAny(): bool
    {
        return PartnerPanel::user()?->isPartnerAdmin() ?? false;
    }

    /**
     * Fila que el administrador del aliado puede gestionar: de su empresa, no administrador y no él mismo.
     */
    public static function canManage(User $record): bool
    {
        $actor = PartnerPanel::user();

        return $actor !== null
            && $actor->isPartnerAdmin()
            && (int) $record->partner_company_id === (int) $actor->partner_company_id
            && ! $record->partner_is_admin
            && ! $record->is($actor);
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return $record instanceof User && static::canManage($record);
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $partnerId = PartnerPanel::companyId();

        return parent::getEloquentQuery()
            ->when($partnerId === null, fn (Builder $query) => $query->whereRaw('1 = 0'))
            ->when($partnerId !== null, fn (Builder $query) => $query->where('partner_company_id', $partnerId));
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre completo')
                    ->required()
                    ->maxLength(255)
                    ->autofocus(),
                TextInput::make('email')
                    ->label('Correo electrónico')
                    ->helperText('Es su usuario para entrar. Le llegará un correo para crear su clave.')
                    ->email()
                    ->required()
                    ->maxLength(255)
                    ->unique(User::class, 'email', ignoreRecord: true)
                    ->validationMessages(['unique' => 'Este correo ya está registrado.'])
                    ->dehydrateStateUsing(fn (?string $state): string => PartnerPanelUserService::normalizeEmail((string) $state)),
                CheckboxList::make('partner_permissions')
                    ->label('Permisos')
                    ->options(PartnerPanelPermission::assignableOptions())
                    ->descriptions(PartnerPanelPermission::descriptions())
                    ->default(array_map(fn (PartnerPanelPermission $permission): string => $permission->value, PartnerPanelPermission::defaults()))
                    ->formatStateUsing(fn (mixed $state): array => PartnerPanelPermission::normalize($state))
                    ->bulkToggleable()
                    ->columns(['default' => 1, 'md' => 2])
                    ->required()
                    ->validationMessages(['required' => 'Marque al menos un permiso.']),
            ])
            ->columns(1);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Usuario')
                    ->description(fn (User $record): string => (string) $record->email)
                    ->badge(fn (User $record): bool => (bool) $record->partner_is_admin)
                    ->color(fn (User $record): ?string => $record->partner_is_admin ? 'primary' : null)
                    ->icon(fn (User $record): ?Heroicon => $record->partner_is_admin ? Heroicon::Star : null)
                    ->tooltip(fn (User $record): ?string => $record->partner_is_admin ? 'Administrador del panel (designado por Farmadoc)' : null)
                    ->weight('medium')
                    ->searchable(['name', 'email'])
                    ->sortable(),
                TextColumn::make('access_status')
                    ->label('Estado')
                    ->state(fn (User $record): string => $record->partnerAccessStatus()['label'])
                    ->color(fn (User $record): string => $record->partnerAccessStatus()['color'])
                    ->badge(),
                TextColumn::make('partner_permissions')
                    ->label('Permisos')
                    ->state(fn (User $record): array => $record->partner_is_admin
                        ? ['Administrador: todos los permisos']
                        : array_map(
                            fn (string $value): string => PartnerPanelPermission::from($value)->label(),
                            $record->partnerPermissionValues(),
                        ))
                    ->badge()
                    ->color('gray')
                    ->limitList(3)
                    ->expandableLimitedList(),
                TextColumn::make('partner_last_login_at')
                    ->label('Último ingreso')
                    ->since()
                    ->dateTimeTooltip('d/m/Y h:i a')
                    ->placeholder('Nunca')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('Estado')
                    ->options(['active' => 'Activo', 'pending' => 'Invitación pendiente', 'suspended' => 'Suspendido'])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'active' => $query->where('partner_user_is_active', true)->whereNotNull('partner_activated_at'),
                        'pending' => $query->where('partner_user_is_active', true)->whereNull('partner_activated_at'),
                        'suspended' => $query->where('partner_user_is_active', false),
                        default => $query,
                    }),
            ])
            ->recordActions([
                ActionGroup::make([
                    EditAction::make()
                        ->label('Editar')
                        ->visible(fn (User $record): bool => static::canManage($record))
                        ->modalWidth('2xl')
                        ->using(fn (User $record, array $data): User => self::runGuarded(fn (): User => app(PartnerPanelUserService::class)->update($record, [
                            'name' => $data['name'],
                            'email' => $data['email'],
                            'permissions' => $data['partner_permissions'] ?? [],
                        ], PartnerPanel::user()))),
                    Action::make('sendAccess')
                        ->label(fn (User $record): string => $record->partner_activated_at === null ? 'Reenviar invitación' : 'Enviar enlace para cambiar clave')
                        ->icon(Heroicon::PaperAirplane)
                        ->color('primary')
                        ->visible(fn (User $record): bool => (bool) $record->partner_user_is_active && static::canManage($record))
                        ->requiresConfirmation()
                        ->modalHeading('Enviar acceso por correo')
                        ->modalDescription(fn (User $record): string => "Se enviará a {$record->email} un enlace para crear su clave. Los enlaces anteriores dejan de funcionar.")
                        ->modalSubmitActionLabel('Enviar')
                        ->action(function (User $record): void {
                            self::runGuarded(fn () => app(PartnerPanelUserService::class)->sendAccess($record, PartnerPanel::user()));

                            Notification::make()->title('Acceso enviado')->body("Se envió el enlace a {$record->email}.")->success()->send();
                        }),
                    Action::make('suspend')
                        ->label('Suspender acceso')
                        ->icon(Heroicon::NoSymbol)
                        ->color('danger')
                        ->visible(fn (User $record): bool => (bool) $record->partner_user_is_active && static::canManage($record))
                        ->requiresConfirmation()
                        ->modalHeading('Suspender acceso')
                        ->modalDescription(fn (User $record): string => "{$record->name} no podrá entrar hasta que lo reactive. Su historial se conserva.")
                        ->modalSubmitActionLabel('Suspender')
                        ->action(function (User $record): void {
                            self::runGuarded(fn () => app(PartnerPanelUserService::class)->suspend($record, PartnerPanel::user()));

                            Notification::make()->title('Acceso suspendido')->success()->send();
                        }),
                    Action::make('reactivate')
                        ->label('Reactivar acceso')
                        ->icon(Heroicon::CheckCircle)
                        ->color('success')
                        ->visible(fn (User $record): bool => ! $record->partner_user_is_active && static::canManage($record))
                        ->requiresConfirmation()
                        ->modalSubmitActionLabel('Reactivar')
                        ->action(function (User $record): void {
                            self::runGuarded(fn () => app(PartnerPanelUserService::class)->reactivate($record, PartnerPanel::user()));

                            Notification::make()->title('Acceso reactivado')->success()->send();
                        }),
                ]),
            ])
            ->defaultSort(fn (Builder $query): Builder => $query->orderByDesc('partner_is_admin')->orderBy('name'))
            ->emptyStateHeading('Aún no hay usuarios')
            ->emptyStateDescription('Cree usuarios para su equipo: cada uno recibirá un correo para crear su clave.');
    }

    /**
     * Ejecuta una operación del servicio mostrando sus reglas de negocio como aviso en lugar de un error.
     *
     * @template T
     *
     * @param  callable(): T  $operation
     * @return T
     */
    public static function runGuarded(callable $operation): mixed
    {
        try {
            return $operation();
        } catch (ValidationException $exception) {
            Notification::make()
                ->title((string) collect($exception->errors())->flatten()->first())
                ->warning()
                ->send();

            throw new Halt;
        }
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageTeamUsers::route('/'),
        ];
    }
}
