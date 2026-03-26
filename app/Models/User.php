<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;

#[Fillable(['name', 'email', 'password', 'branch_id', 'roles'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, TwoFactorAuthenticatable;

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'roles' => 'array',
        ];
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        return Str::of($this->name)
            ->explode(' ')
            ->take(2)
            ->map(fn ($word) => Str::substr($word, 0, 1))
            ->implode('');
    }

    public function canAccessPanel(Panel $panel): bool
    {
        return true;
    }

    /**
     * Si el array JSON `roles` incluye «ADMINISTRADOR», el usuario puede ver datos de todas las sucursales.
     */
    public function isAdministrator(): bool
    {
        $roles = $this->roles;
        if (! is_array($roles)) {
            return false;
        }

        if (in_array('ADMINISTRADOR', $roles, true)) {
            return true;
        }

        return false;
    }

    /**
     * Acceso al módulo de Marketing (panel Farmaadmin): administradores o rol MARKETING.
     */
    public function canAccessMarketingModule(): bool
    {
        if ($this->isAdministrator()) {
            return true;
        }

        $roles = $this->roles;
        if (! is_array($roles)) {
            return false;
        }

        return in_array('MARKETING', $roles, true);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Rol::class);
    }
}
