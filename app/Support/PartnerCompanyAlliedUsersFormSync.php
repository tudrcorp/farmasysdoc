<?php

namespace App\Support;

use App\Models\PartnerCompany;
use App\Models\PartnerCompanyUser;
use App\Models\User;
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
            if (blank($row['password'] ?? null)) {
                throw ValidationException::withMessages([
                    "partner_users.{$index}.password" => 'Defina una contraseña para cada usuario nuevo.',
                ]);
            }
        }

        self::assertUniqueEmailsInSubmission($rows);
        self::assertEmailsAvailableInDatabase($rows);
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
            } elseif (blank($row['password'] ?? null)) {
                throw ValidationException::withMessages([
                    "partner_users.{$index}.password" => 'Defina una contraseña para cada usuario nuevo.',
                ]);
            }
        }

        self::assertUniqueEmailsInSubmission($rows);
        self::assertEmailsAvailableInDatabase($rows);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function createUsers(PartnerCompany $company, array $rows): void
    {
        if ($rows === []) {
            return;
        }

        $code = (string) $company->code;

        foreach ($rows as $row) {
            $user = User::query()->create([
                'name' => (string) $row['name'],
                'email' => strtolower(trim((string) $row['email'])),
                'password' => (string) $row['password'],
                'branch_id' => null,
                'partner_company_id' => $company->getKey(),
                'partner_company_code' => $code,
                'partner_user_is_active' => (bool) ($row['is_active'] ?? true),
                'roles' => null,
            ]);

            PartnerCompanyUser::query()->create([
                'partner_company_id' => $company->getKey(),
                'user_id' => $user->getKey(),
            ]);
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     */
    public static function syncUsers(PartnerCompany $company, array $rows): void
    {
        $code = (string) $company->code;

        $existingLinks = PartnerCompanyUser::query()
            ->where('partner_company_id', $company->getKey())
            ->get();

        if ($rows === []) {
            foreach ($existingLinks as $link) {
                PartnerCompanyUser::query()->whereKey($link->getKey())->delete();
                User::query()->whereKey($link->user_id)->update([
                    'partner_company_id' => null,
                    'partner_company_code' => null,
                    'partner_user_is_active' => false,
                ]);
            }

            return;
        }

        $keepUserIds = collect();

        foreach ($rows as $row) {
            $userId = (int) ($row['user_id'] ?? 0);

            if ($userId > 0) {
                $link = $existingLinks->firstWhere('user_id', $userId);
                if ($link === null) {
                    continue;
                }

                $user = User::query()->find($userId);
                if ($user === null) {
                    continue;
                }

                $payload = [
                    'name' => (string) $row['name'],
                    'email' => strtolower(trim((string) $row['email'])),
                    'partner_company_id' => $company->getKey(),
                    'partner_company_code' => $code,
                    'partner_user_is_active' => (bool) ($row['is_active'] ?? true),
                ];

                if (filled($row['password'] ?? null)) {
                    $payload['password'] = (string) $row['password'];
                }

                $user->update($payload);
                $keepUserIds->push($userId);
            } else {
                $user = User::query()->create([
                    'name' => (string) $row['name'],
                    'email' => strtolower(trim((string) $row['email'])),
                    'password' => (string) $row['password'],
                    'branch_id' => null,
                    'partner_company_id' => $company->getKey(),
                    'partner_company_code' => $code,
                    'partner_user_is_active' => (bool) ($row['is_active'] ?? true),
                    'roles' => null,
                ]);

                PartnerCompanyUser::query()->create([
                    'partner_company_id' => $company->getKey(),
                    'user_id' => $user->getKey(),
                ]);

                $keepUserIds->push((int) $user->getKey());
            }
        }

        $idsToDetach = $existingLinks->pluck('user_id')->diff($keepUserIds->unique()->values());

        foreach ($idsToDetach as $detachUserId) {
            PartnerCompanyUser::query()
                ->where('partner_company_id', $company->getKey())
                ->where('user_id', $detachUserId)
                ->delete();

            User::query()->whereKey($detachUserId)->update([
                'partner_company_id' => null,
                'partner_company_code' => null,
                'partner_user_is_active' => false,
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
}
