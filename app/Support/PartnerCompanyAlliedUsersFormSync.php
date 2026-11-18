<?php

namespace App\Support;

use App\Enums\PartnerPanelPermission;
use App\Models\PartnerCompany;
use App\Models\PartnerCompanyUser;
use App\Models\User;
use App\Services\Partners\PartnerPanelUserService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

final class PartnerCompanyAlliedUsersFormSync
{
    /**
     * @param  array<int, mixed>  $rows
     * @return array<int, array<string, mixed>>
     */
    public static function normalizeRows(array $rows): array
    {
        return collect($rows)
            ->map(function (mixed $row): ?array {
                if (! is_array($row)) {
                    return null;
                }

                return $row;
            })
            ->filter(function (?array $row): bool {
                if ($row === null) {
                    return false;
                }

                return filled($row['name'] ?? null) || filled($row['email'] ?? null);
            })
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function validateRowsForCreate(array $rows): void
    {
        if ($rows === []) {
            return;
        }

        foreach ($rows as $index => $row) {
            if (blank($row['name'] ?? null) || blank($row['email'] ?? null)) {
                throw ValidationException::withMessages([
                    "partner_users.{$index}.name" => 'Nombre y correo son obligatorios para cada usuario.',
                    "partner_users.{$index}.email" => 'Nombre y correo son obligatorios para cada usuario.',
                ]);
            }
        }

        self::assertUniqueEmailsInSubmission($rows);
        self::assertEmailsAvailableInDatabase($rows);
        self::assertHasActiveAdmin($rows);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function validateRowsForUpdate(PartnerCompany $company, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        foreach ($rows as $index => $row) {
            if (blank($row['name'] ?? null) || blank($row['email'] ?? null)) {
                throw ValidationException::withMessages([
                    "partner_users.{$index}.name" => 'Nombre y correo son obligatorios para cada usuario.',
                    "partner_users.{$index}.email" => 'Nombre y correo son obligatorios para cada usuario.',
                ]);
            }
            $userId = (int) ($row['user_id'] ?? 0);
            if ($userId > 0) {
                $belongs = PartnerCompanyUser::query()
                    ->where('partner_company_id', $company->getKey())
                    ->where('user_id', $userId)
                    ->exists();
                if (! $belongs) {
                    throw ValidationException::withMessages([
                        "partner_users.{$index}.user_id" => 'Usuario no válido para esta compañía aliada.',
                    ]);
                }
            }
        }

        self::assertUniqueEmailsInSubmission($rows);
        self::assertEmailsAvailableInDatabase($rows);
        self::assertHasActiveAdmin($rows);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function createUsers(PartnerCompany $company, array $rows): void
    {
        if ($rows === [] || ! self::actorManagesPartnerUsers()) {
            return;
        }

        foreach ($rows as $index => $row) {
            self::createUser($company, $row, $index);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function syncUsers(PartnerCompany $company, array $rows): void
    {
        if (! self::actorManagesPartnerUsers()) {
            return;
        }

        $service = app(PartnerPanelUserService::class);
        $code = (string) $company->code;

        $existingLinks = PartnerCompanyUser::query()
            ->where('partner_company_id', $company->getKey())
            ->get();

        $keepUserIds = collect();

        foreach ($rows as $index => $row) {
            $userId = (int) ($row['user_id'] ?? 0);

            if ($userId <= 0) {
                $keepUserIds->push((int) self::createUser($company, $row, $index)->getKey());

                continue;
            }

            if ($existingLinks->firstWhere('user_id', $userId) === null) {
                continue;
            }

            $user = User::query()->find($userId);
            if ($user === null) {
                continue;
            }

            $service->update($user, [
                'name' => trim((string) $row['name']),
                'email' => (string) $row['email'],
                'is_active' => self::resolvePartnerUserActivationForActor((bool) ($row['is_active'] ?? true), $index, $user),
                'is_admin' => (bool) ($row['is_admin'] ?? false),
                'permissions' => $row['permissions'] ?? [],
            ], Auth::user() instanceof User ? Auth::user() : null);

            if ((int) $user->partner_company_id !== (int) $company->getKey()) {
                $user->forceFill(['partner_company_id' => $company->getKey(), 'partner_company_code' => $code])->save();
            }

            $keepUserIds->push($userId);
        }

        $idsToDetach = $existingLinks->pluck('user_id')->diff($keepUserIds->unique()->values());

        $actor = Auth::user() instanceof User ? Auth::user() : null;
        User::query()->whereKey($idsToDetach->all())->get()->each(fn (User $user) => $service->detach($user, $actor));
    }

    /**
     * Crea el usuario con la clave estándar: entra de inmediato y decide si la cambia.
     *
     * @param  array<string, mixed>  $row
     */
    private static function createUser(PartnerCompany $company, array $row, int $index): User
    {
        return app(PartnerPanelUserService::class)->create($company, [
            'name' => (string) $row['name'],
            'email' => (string) $row['email'],
            'permissions' => $row['permissions'] ?? PartnerPanelPermission::defaults(),
            'is_active' => self::resolvePartnerUserActivationForActor((bool) ($row['is_active'] ?? true), $index),
            'is_admin' => (bool) ($row['is_admin'] ?? false),
        ], sendAccess: false, withDefaultPassword: true, actor: Auth::user() instanceof User ? Auth::user() : null);
    }

    /**
     * Toda empresa con usuarios en el panel debe tener al menos un administrador activo.
     *
     * @param  array<int, array<string, mixed>>  $rows
     */
    private static function assertHasActiveAdmin(array $rows): void
    {
        $hasAdmin = collect($rows)->contains(fn (array $row): bool => (bool) ($row['is_admin'] ?? false) && (bool) ($row['is_active'] ?? true));

        if (! $hasAdmin) {
            throw ValidationException::withMessages([
                'partner_users' => 'Marque al menos un usuario activo como «Administrador del panel dale»: es quien gestionará a los usuarios de la empresa.',
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private static function assertUniqueEmailsInSubmission(array $rows): void
    {
        $emails = collect($rows)->map(fn (array $r): string => strtolower(trim((string) ($r['email'] ?? ''))));
        if ($emails->unique()->count() !== $emails->count()) {
            throw ValidationException::withMessages([
                'partner_users' => 'Hay correos electrónicos duplicados en la lista de usuarios.',
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    private static function assertEmailsAvailableInDatabase(array $rows): void
    {
        foreach ($rows as $index => $row) {
            $email = strtolower(trim((string) ($row['email'] ?? '')));
            $ignoreId = (int) ($row['user_id'] ?? 0);

            $query = User::query()->where('email', $email);
            if ($ignoreId > 0) {
                $query->whereKeyNot($ignoreId);
            }

            if ($query->exists()) {
                throw ValidationException::withMessages([
                    "partner_users.{$index}.email" => 'Este correo ya está registrado en el sistema.',
                ]);
            }
        }
    }

    /**
     * Solo administradores y gerentes ven la sección de usuarios; para el resto no se toca nada.
     */
    public static function actorManagesPartnerUsers(): bool
    {
        $authUser = Auth::user();

        return $authUser instanceof User && ($authUser->isAdministrator() || $authUser->isManager());
    }

    private static function shouldApplyManagerActivationRestriction(): bool
    {
        $authUser = Auth::user();

        return $authUser instanceof User
            && $authUser->isManager()
            && ! $authUser->isAdministrator();
    }

    private static function resolvePartnerUserActivationForActor(
        bool $requestedActive,
        int $position,
        ?User $existingUser = null
    ): bool {
        if (! self::shouldApplyManagerActivationRestriction()) {
            return $requestedActive;
        }

        if ($position < 2) {
            return $requestedActive;
        }

        if ($existingUser instanceof User) {
            if (! $requestedActive) {
                return false;
            }

            return (bool) ($existingUser->partner_user_is_active ?? false);
        }

        return false;
    }
}
