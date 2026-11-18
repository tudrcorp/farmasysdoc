<?php

namespace Database\Factories;

use App\Enums\PartnerPanelPermission;
use App\Models\Branch;
use App\Models\PartnerCompany;
use App\Models\PartnerCompanyUser;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'branch_id' => Branch::factory(),
            'partner_company_id' => null,
            'partner_company_code' => null,
            'partner_user_is_active' => true,
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'two_factor_secret' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => null,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Indicate that the model has two-factor authentication configured.
     */
    public function withTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => [
            'two_factor_secret' => encrypt('secret'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-code-1'])),
            'two_factor_confirmed_at' => now(),
        ]);
    }

    /**
     * Usuario activo del panel de aliados (dale · Aliados), vinculado a la compañía.
     *
     * @param  list<PartnerPanelPermission>|null  $permissions  null = todos los permisos asignables.
     * @param  bool  $admin  administrador del panel (todos los permisos y gestión de usuarios).
     */
    public function partnerPanelUser(PartnerCompany $partner, ?array $permissions = null, bool $admin = false): static
    {
        return $this->state(fn (): array => [
            'partner_is_admin' => $admin,
            'branch_id' => null,
            'roles' => null,
            'partner_company_id' => $partner->getKey(),
            'partner_company_code' => $partner->code,
            'partner_user_is_active' => true,
            'partner_permissions' => array_map(
                fn (PartnerPanelPermission $permission): string => $permission->value,
                $permissions ?? PartnerPanelPermission::assignable(),
            ),
            'partner_activated_at' => now(),
        ])->afterCreating(function (User $user) use ($partner): void {
            PartnerCompanyUser::query()->create([
                'partner_company_id' => $partner->getKey(),
                'user_id' => $user->getKey(),
            ]);
        });
    }
}
