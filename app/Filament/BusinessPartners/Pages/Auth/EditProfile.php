<?php

namespace App\Filament\BusinessPartners\Pages\Auth;

use App\Enums\PartnerPanelPermission;
use App\Filament\Pages\Auth\EditProfile as FarmaadminEditProfile;
use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Component;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Contracts\Support\Htmlable;

/**
 * «Mi perfil» de dale · Aliados: tarjeta de identidad con foto, datos personales, clave con
 * medidor de seguridad en vivo y los permisos del usuario. Reutiliza el guardado del perfil de
 * Farmaadmin (foto, nombre, correo y clave con verificación de la clave actual).
 */
class EditProfile extends FarmaadminEditProfile
{
    public function getTitle(): string|Htmlable
    {
        return 'Mi perfil';
    }

    public function getHeading(): string|Htmlable
    {
        return 'Mi perfil';
    }

    public function getSubheading(): ?string
    {
        return 'Sus datos de acceso a dale · Aliados.';
    }

    /**
     * Etiquetas encima de los campos (Filament las pone a la izquierda en el perfil no simple).
     */
    public function defaultForm(Schema $schema): Schema
    {
        return parent::defaultForm($schema)->inlineLabel(false);
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Grid::make(['default' => 1, 'lg' => 3])
                    ->schema([
                        Group::make([
                            Section::make()
                                ->schema([
                                    $this->getAvatarFormComponent(),
                                    View::make('filament.business-partners.profile.summary')
                                        ->viewData(fn (): array => $this->summary()),
                                ])
                                ->extraAttributes(['class' => 'dale-profile-card']),
                        ])->columnSpan(['lg' => 1]),

                        Group::make([
                            Section::make('Datos personales')
                                ->description('Su nombre y el correo con el que entra al panel.')
                                ->icon(Heroicon::OutlinedUserCircle)
                                ->schema([
                                    Grid::make(['default' => 1, 'md' => 2])
                                        ->schema([
                                            $this->getNameFormComponent()
                                                ->label('Nombre completo')
                                                ->prefixIcon(Heroicon::User),
                                            $this->getEmailFormComponent()
                                                ->label('Correo electrónico')
                                                ->prefixIcon(Heroicon::Envelope)
                                                ->helperText('Es su usuario para entrar. Si lo cambia, le pediremos confirmarlo desde el nuevo correo.'),
                                        ]),
                                ]),

                            Section::make('Clave de acceso')
                                ->description('Déjela en blanco si no quiere cambiarla.')
                                ->icon(Heroicon::OutlinedKey)
                                ->schema([
                                    View::make('filament.business-partners.profile.default-password-notice')
                                        ->visible(fn (): bool => $this->currentUser()?->usesDefaultPartnerPassword() ?? false),
                                    Grid::make(['default' => 1, 'md' => 2])
                                        ->schema([
                                            $this->getPasswordFormComponent()
                                                ->label('Nueva clave')
                                                ->prefixIcon(Heroicon::LockClosed)
                                                ->belowContent(View::make('filament.business-partners.profile.password-strength')),
                                            $this->getPasswordConfirmationFormComponent()
                                                ->label('Repita la nueva clave')
                                                ->prefixIcon(Heroicon::LockClosed),
                                        ]),
                                    $this->getCurrentPasswordFormComponent()
                                        ->label('Clave actual')
                                        ->prefixIcon(Heroicon::ShieldCheck)
                                        ->belowContent('Por seguridad, confirme su clave actual para guardar estos cambios.'),
                                ]),

                            Section::make('Lo que puede hacer en el panel')
                                ->description(fn (): string => $this->currentUser()?->isPartnerAdmin()
                                    ? 'Es administrador del panel: tiene todos los permisos y gestiona a su equipo.'
                                    : 'Sus permisos los asigna el administrador del panel de su empresa.')
                                ->icon(Heroicon::OutlinedShieldCheck)
                                ->collapsible()
                                ->schema([
                                    View::make('filament.business-partners.profile.permissions')
                                        ->viewData(fn (): array => ['permissions' => $this->permissions()]),
                                ]),
                        ])->columnSpan(['lg' => 2]),
                    ]),
            ]);
    }

    protected function getAvatarFormComponent(): Component
    {
        /** @var FileUpload $upload */
        $upload = parent::getAvatarFormComponent();

        return $upload
            ->hiddenLabel()
            ->helperText(null)
            ->imagePreviewHeight('128')
            ->loadingIndicatorPosition('center')
            ->removeUploadedFileButtonPosition('center bottom')
            ->uploadButtonPosition('center bottom')
            ->uploadProgressIndicatorPosition('center bottom')
            ->placeholder('Subir foto');
    }

    protected function getSavedNotification(): ?Notification
    {
        return Notification::make()
            ->success()
            ->title('Perfil actualizado')
            ->body('Sus cambios se guardaron.');
    }

    private function currentUser(): ?User
    {
        $user = $this->getUser();

        return $user instanceof User ? $user->fresh() : null;
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(): array
    {
        $user = $this->currentUser();
        $company = $user?->partnerCompany;
        $status = $user?->partnerAccessStatus() ?? ['label' => '—', 'color' => 'gray', 'key' => 'active'];

        return [
            'name' => (string) $user?->name,
            'email' => (string) $user?->email,
            'company' => (string) ($company?->trade_name ?: $company?->legal_name),
            'status' => $status,
            'usesDefaultPassword' => $user?->usesDefaultPartnerPassword() ?? false,
            'canManageUsers' => $user?->isPartnerAdmin() ?? false,
            'facts' => array_values(array_filter([
                ['label' => 'Último ingreso', 'value' => $user?->partner_last_login_at?->diffForHumans() ?? 'Primera vez', 'icon' => 'heroicon-m-clock'],
                ['label' => 'Miembro desde', 'value' => $user?->created_at?->translatedFormat('j M Y') ?? '—', 'icon' => 'heroicon-m-calendar'],
                ['label' => 'Permisos', 'value' => ($user?->isPartnerAdmin() ? 'Todos (administrador)' : count($user?->effectivePartnerPermissionValues() ?? []).' de '.count(PartnerPanelPermission::values())), 'icon' => 'heroicon-m-shield-check'],
            ])),
        ];
    }

    /**
     * @return list<array{label: string, description: string, granted: bool}>
     */
    private function permissions(): array
    {
        $user = $this->currentUser();
        $cases = $user?->isPartnerAdmin() ? PartnerPanelPermission::cases() : PartnerPanelPermission::assignable();

        return array_map(fn (PartnerPanelPermission $permission): array => [
            'label' => $permission->label(),
            'description' => $permission->description(),
            'granted' => $user?->hasPartnerPermission($permission) ?? false,
        ], $cases);
    }
}
