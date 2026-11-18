<?php

namespace App\Services\Partners;

use App\Enums\PartnerPanelPermission;
use App\Jobs\SendPartnerPanelAccessJob;
use App\Models\PartnerCompany;
use App\Models\PartnerCompanyUser;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\Partners\PartnerPanel;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Alta, cambios, suspensión e invitación de los usuarios del panel de aliados.
 *
 * Reglas (se validan aquí, no solo en la interfaz):
 * - Farmadoc (personal interno) puede todo, incluido designar o retirar administradores.
 * - Un administrador del panel del aliado gestiona a los usuarios de SU empresa, pero no puede
 *   designar administradores, ni modificar a otros administradores, ni modificarse a sí mismo.
 * - Los demás usuarios del aliado no gestionan usuarios.
 * Cada cambio queda en la bitácora del aliado ({@see PartnerPanelActivityRecorder}).
 */
class PartnerPanelUserService
{
    /**
     * Segundos mínimos entre dos envíos de acceso al mismo usuario.
     */
    public const RESEND_COOLDOWN_SECONDS = 60;

    /**
     * @param  array{name: string, email: string, permissions?: mixed, is_active?: bool}  $data
     * @param  bool  $withDefaultPassword  true = nace con la clave estándar ({@see self::defaultPassword()}) y
     *                                     queda activo de inmediato; false = sin clave conocida, la crea con el enlace.
     */
    public function create(PartnerCompany $company, array $data, bool $sendAccess = true, bool $withDefaultPassword = false, ?User $actor = null): User
    {
        if ($actor?->isPartnerCompanyUser()) {
            $this->assertPartnerAdminOf($actor, (int) $company->getKey());
        }

        $isAdmin = ! ($actor?->isPartnerCompanyUser() ?? false) && (bool) ($data['is_admin'] ?? false);
        $email = self::normalizeEmail($data['email']);
        $this->assertEmailAvailable($email);

        $user = DB::transaction(function () use ($company, $data, $email, $withDefaultPassword, $isAdmin): User {
            $user = User::query()->create([
                'name' => trim($data['name']),
                'email' => $email,
                'password' => $withDefaultPassword ? self::defaultPassword() : Str::password(40),
                'partner_activated_at' => $withDefaultPassword ? now() : null,
                'branch_id' => null,
                'partner_company_id' => $company->getKey(),
                'partner_company_code' => (string) $company->code,
                'partner_user_is_active' => (bool) ($data['is_active'] ?? true),
                'partner_is_admin' => $isAdmin,
                'partner_permissions' => PartnerPanelPermission::normalizeForCompany($data['permissions'] ?? PartnerPanelPermission::defaults(), $company),
                'roles' => null,
            ]);

            PartnerCompanyUser::query()->create([
                'partner_company_id' => $company->getKey(),
                'user_id' => $user->getKey(),
            ]);

            return $user;
        });

        PartnerPanelActivityRecorder::record(
            (int) $company->getKey(),
            'user_created',
            ($isAdmin ? 'Creó al administrador del panel ' : 'Creó al usuario ').$user->name.' ('.$user->email.')'
                .($isAdmin ? '' : ' con permisos: '.self::permissionLabels($user->partnerPermissionValues())),
            $actor,
            $user,
            (string) $user->name,
            ['is_admin' => $isAdmin, 'permissions' => $user->partnerPermissionValues()],
        );

        if ($sendAccess && $user->partner_user_is_active) {
            $this->dispatchAccess($user);
        }

        return $user;
    }

    /**
     * @param  array{name?: string, email?: string, permissions?: mixed, is_active?: bool, is_admin?: bool}  $data
     */
    public function update(User $user, array $data, ?User $actor = null): User
    {
        $this->assertPartnerUser($user);
        $this->assertActorCanManage($actor, $user);

        $before = [
            'name' => (string) $user->name,
            'email' => (string) $user->email,
            'permissions' => $user->partnerPermissionValues(),
            'is_active' => (bool) $user->partner_user_is_active,
            'is_admin' => (bool) $user->partner_is_admin,
        ];
        $payload = [];

        if (array_key_exists('name', $data)) {
            $payload['name'] = trim((string) $data['name']);
        }

        if (array_key_exists('email', $data)) {
            $email = self::normalizeEmail((string) $data['email']);
            $this->assertEmailAvailable($email, $user);
            $payload['email'] = $email;
        }

        if (array_key_exists('permissions', $data)) {
            $payload['partner_permissions'] = PartnerPanelPermission::normalizeForCompany($data['permissions'], $user->partnerCompany);
        }

        if (array_key_exists('is_active', $data)) {
            $payload['partner_user_is_active'] = (bool) $data['is_active'];
        }

        if (array_key_exists('is_admin', $data) && ! ($actor?->isPartnerCompanyUser() ?? false)) {
            $payload['partner_is_admin'] = (bool) $data['is_admin'];
        }

        $user->fill($payload);

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $this->recordChanges($user, $before, $actor);

        return $user;
    }

    public function suspend(User $user, ?User $actor = null): void
    {
        $this->update($user, ['is_active' => false], $actor);
    }

    public function reactivate(User $user, ?User $actor = null): void
    {
        $this->update($user, ['is_active' => true], $actor);
    }

    /**
     * Envía (o reenvía) el correo con el enlace para crear la clave.
     *
     * @throws ValidationException si el usuario está suspendido o se acaba de enviar.
     */
    public function sendAccess(User $user, ?User $actor = null): void
    {
        $this->assertPartnerUser($user);

        if ($actor?->isPartnerCompanyUser() && ! $actor->is($user)) {
            $this->assertActorCanManage($actor, $user);
        }

        if (! $user->partner_user_is_active) {
            throw ValidationException::withMessages(['user' => 'El usuario está suspendido: reactívelo antes de enviarle el acceso.']);
        }

        $sentAt = $user->partner_access_sent_at;

        if ($sentAt !== null && $sentAt->diffInSeconds(now()) < self::RESEND_COOLDOWN_SECONDS) {
            throw ValidationException::withMessages(['user' => 'El acceso se envió hace un momento. Espere un minuto antes de reenviarlo.']);
        }

        $this->dispatchAccess($user);

        PartnerPanelActivityRecorder::record((int) $user->partner_company_id, 'access_sent', 'Envió el acceso por correo a '.$user->name.' ('.$user->email.')', $actor, $user, (string) $user->name);
    }

    /**
     * Genera el enlace para crear/cambiar la clave sin enviarlo por correo (solo administradores,
     * para cuentas de prueba o cuando el correo no llega). Invalida los enlaces anteriores y queda
     * registrado en la auditoría.
     *
     * @throws ValidationException si el usuario no puede entrar al panel.
     */
    public function accessLink(User $user, User $actor): string
    {
        $this->assertPartnerUser($user);

        if (! $actor->isAdministrator()) {
            throw ValidationException::withMessages(['user' => 'Solo un administrador puede generar enlaces de acceso.']);
        }

        if (! $user->canAccessPartnerPanel()) {
            throw ValidationException::withMessages(['user' => (string) $user->partnerPanelAccessDeniedReason()]);
        }

        $token = Password::broker(PartnerPanel::PASSWORD_BROKER)->createToken($user);

        AuditLogger::record(
            event: 'partner_access_link_generated',
            description: "Enlace de acceso a dale · Aliados generado para {$user->email}",
            auditableType: User::class,
            auditableId: $user->getKey(),
            auditableLabel: (string) $user->email,
            user: $actor,
        );

        return Filament::getPanel(User::PARTNER_PANEL_ID)->getResetPasswordUrl($token, $user);
    }

    /**
     * Quita el vínculo con el aliado sin borrar al usuario (conserva la auditoría de sus pedidos).
     */
    public function detach(User $user, ?User $actor = null): void
    {
        $partnerId = (int) $user->partner_company_id;

        PartnerPanelActivityRecorder::record($partnerId, 'user_removed', 'Retiró a '.$user->name.' ('.$user->email.') del panel', $actor, $user, (string) $user->name);

        DB::transaction(function () use ($user): void {
            PartnerCompanyUser::query()->where('user_id', $user->getKey())->delete();

            $user->forceFill([
                'partner_company_id' => null,
                'partner_company_code' => null,
                'partner_user_is_active' => false,
                'partner_is_admin' => false,
                'partner_permissions' => null,
            ])->save();
        });
    }

    /**
     * Clave estándar de los usuarios creados por Farmadoc (config `partner_panel.default_password`).
     */
    public static function defaultPassword(): string
    {
        return (string) config('partner_panel.default_password');
    }

    public static function normalizeEmail(string $email): string
    {
        return Str::lower(trim($email));
    }

    private function dispatchAccess(User $user): void
    {
        $user->forceFill(['partner_access_sent_at' => now()])->saveQuietly();

        SendPartnerPanelAccessJob::dispatch((int) $user->getKey(), $user->partner_activated_at === null);
    }

    private function assertEmailAvailable(string $email, ?User $ignore = null): void
    {
        $taken = User::query()
            ->where('email', $email)
            ->when($ignore !== null, fn ($query) => $query->whereKeyNot($ignore->getKey()))
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages(['email' => 'Este correo ya está registrado en el sistema.']);
        }
    }

    private function assertPartnerUser(User $user): void
    {
        if (! $user->isPartnerCompanyUser()) {
            throw ValidationException::withMessages(['user' => 'El usuario no pertenece a ninguna compañía aliada.']);
        }
    }

    /**
     * Un usuario del aliado solo gestiona usuarios si es administrador de esa misma empresa, y
     * nunca a otro administrador ni a sí mismo. El personal de Farmadoc no tiene estas limitaciones.
     */
    private function assertActorCanManage(?User $actor, User $target): void
    {
        if ($actor === null || ! $actor->isPartnerCompanyUser()) {
            return;
        }

        $this->assertPartnerAdminOf($actor, (int) $target->partner_company_id);

        if ($actor->is($target)) {
            throw ValidationException::withMessages(['user' => 'No puede modificar su propio usuario desde aquí. Use «Mi perfil» para sus datos.']);
        }

        if ($target->partner_is_admin) {
            throw ValidationException::withMessages(['user' => 'Los administradores del panel solo los gestiona Farmadoc.']);
        }
    }

    private function assertPartnerAdminOf(User $actor, int $partnerCompanyId): void
    {
        if (! $actor->isPartnerAdmin() || (int) $actor->partner_company_id !== $partnerCompanyId) {
            throw ValidationException::withMessages(['user' => 'Solo un administrador del panel de su empresa puede gestionar usuarios.']);
        }
    }

    /**
     * @param  array{name: string, email: string, permissions: list<string>, is_active: bool, is_admin: bool}  $before
     */
    private function recordChanges(User $user, array $before, ?User $actor): void
    {
        $partnerId = (int) $user->partner_company_id;
        $label = (string) $user->name;

        if ($before['is_admin'] !== (bool) $user->partner_is_admin) {
            PartnerPanelActivityRecorder::record(
                $partnerId,
                $user->partner_is_admin ? 'admin_granted' : 'admin_revoked',
                ($user->partner_is_admin ? 'Designó a ' : 'Retiró como administrador a ').$label.($user->partner_is_admin ? ' como administrador del panel' : ''),
                $actor,
                $user,
                $label,
            );
        }

        if ($before['is_active'] !== (bool) $user->partner_user_is_active) {
            PartnerPanelActivityRecorder::record(
                $partnerId,
                $user->partner_user_is_active ? 'user_reactivated' : 'user_suspended',
                ($user->partner_user_is_active ? 'Reactivó el acceso de ' : 'Suspendió el acceso de ').$label,
                $actor,
                $user,
                $label,
            );
        }

        $after = $user->partnerPermissionValues();
        $added = array_values(array_diff($after, $before['permissions']));
        $removed = array_values(array_diff($before['permissions'], $after));

        if ($added !== [] || $removed !== []) {
            $parts = [];
            if ($added !== []) {
                $parts[] = 'otorgó '.self::permissionLabels($added);
            }
            if ($removed !== []) {
                $parts[] = 'quitó '.self::permissionLabels($removed);
            }

            PartnerPanelActivityRecorder::record(
                $partnerId,
                'permissions_changed',
                'Permisos de '.$label.': '.implode('; ', $parts),
                $actor,
                $user,
                $label,
                ['added' => $added, 'removed' => $removed],
            );
        }

        $profileChanges = [];
        if ($before['name'] !== (string) $user->name) {
            $profileChanges[] = 'nombre «'.$before['name'].'» → «'.$user->name.'»';
        }
        if ($before['email'] !== (string) $user->email) {
            $profileChanges[] = 'correo '.$before['email'].' → '.$user->email;
        }

        if ($profileChanges !== []) {
            PartnerPanelActivityRecorder::record($partnerId, 'user_updated', 'Modificó a '.$label.': '.implode('; ', $profileChanges), $actor, $user, $label);
        }
    }

    /**
     * @param  list<string>  $values
     */
    public static function permissionLabels(array $values): string
    {
        $labels = array_map(fn (string $value): string => PartnerPanelPermission::tryFrom($value)?->label() ?? $value, $values);

        return $labels === [] ? 'ninguno' : implode(', ', $labels);
    }
}
