<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Support\Filament\FarmaadminMenuAccessCatalog;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Fortify\TwoFactorAuthenticatable;
use Laravel\Sanctum\HasApiTokens;

#[Fillable(['name', 'email', 'password', 'branch_id', 'partner_company_id', 'partner_company_code', 'partner_user_is_active', 'roles', 'delivery_photo_path', 'delivery_identity_document', 'delivery_mobile_phone', 'whatsapp_phone'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements FilamentUser
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable, TwoFactorAuthenticatable;

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
            'partner_user_is_active' => 'boolean',
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
        if ($this->isPartnerCompanyUser() && ! $this->isAdministrator() && ! ($this->partner_user_is_active ?? true)) {
            return false;
        }

        return true;
    }

    /**
     * Si el array JSON `roles` incluye «ADMINISTRADOR», el usuario puede ver datos de todas las sucursales.
     */
    public function isAdministrator(): bool
    {
        return $this->hasRole('ADMINISTRADOR');
    }

    /**
     * Rol gerencial para gestión comercial de aliados (sin privilegios de administrador).
     */
    public function isManager(): bool
    {
        return $this->hasRole('GERENCIA') || $this->hasRole('GERENTE');
    }

    /**
     * Rol de logística: opera a nivel empresa, sin sucursal asignada ({@see BranchAuthScope}).
     */
    public function isDeliveryUser(): bool
    {
        $roles = $this->roles;
        if (! is_array($roles)) {
            return false;
        }

        return in_array('DELIVERY', $roles, true);
    }

    /**
     * URL pública de la foto de repartidor (aliados / ficha de pedido), o null si no hay archivo.
     */
    public function deliveryPhotoPublicUrl(): ?string
    {
        if (blank($this->delivery_photo_path)) {
            return null;
        }

        return Storage::disk('public')->url($this->delivery_photo_path);
    }

    /**
     * Normaliza datos de formulario: usuarios con rol DELIVERY no llevan sucursal.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function stripBranchIdWhenDeliveryRole(array $data): array
    {
        $roles = $data['roles'] ?? null;
        if (is_array($roles) && in_array('DELIVERY', $roles, true)) {
            $data['branch_id'] = null;
        }

        return $data;
    }

    /**
     * Usuario vinculado a una compañía aliada (panel u operación en nombre del aliado).
     */
    public function isPartnerCompanyUser(): bool
    {
        return filled($this->partner_company_id);
    }

    /**
     * La compañía aliada del usuario tiene cupo de crédito asignado mayor que cero.
     */
    public function hasPartnerCompanyAssignedCredit(): bool
    {
        if (! $this->isPartnerCompanyUser()) {
            return false;
        }

        return PartnerCompany::query()
            ->whereKey((int) $this->partner_company_id)
            ->whereNotNull('assigned_credit_limit')
            ->where('assigned_credit_limit', '>', 0)
            ->exists();
    }

    /**
     * Etiqueta del grupo de navegación de operación por sucursal (panel Farmaadmin):
     * administradores ven «Farmadoc®»; el resto, el nombre de su sucursal.
     */
    public function navigationOperationsGroupLabel(): string
    {
        if ($this->isAdministrator() || $this->isDeliveryUser()) {
            return 'Farmadoc®';
        }

        return $this->branch?->name ?? 'Farmadoc®';
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
     * Acceso por ítem de menú del panel Farmaadmin según los roles activos del usuario.
     */
    public function canAccessFarmaadminMenuKey(string $menuKey): bool
    {
        return in_array($menuKey, $this->resolvedAllowedFarmaadminMenuItems(), true);
    }

    /**
     * @return list<string>
     */
    public function resolvedAllowedFarmaadminMenuItems(): array
    {
        if ($this->isAdministrator()) {
            return FarmaadminMenuAccessCatalog::allKeys();
        }

        $roles = $this->roles;

        if (! is_array($roles) || $roles === []) {
            return [];
        }

        $resolved = [];
        $allKeys = FarmaadminMenuAccessCatalog::allKeys();

        $records = Rol::query()
            ->whereIn('name', $roles)
            ->where('is_active', true)
            ->get(['allowed_menu_items']);

        foreach ($records as $record) {
            if ($record->allowed_menu_items === null) {
                return $allKeys;
            }

            if (! is_array($record->allowed_menu_items)) {
                continue;
            }

            $resolved = [...$resolved, ...$record->allowed_menu_items];
        }

        return array_values(array_unique($resolved));
    }

    /**
     * Opciones por defecto para alta/edición de roles.
     *
     * @return list<string>
     */
    public static function defaultAllowedMenuItems(): array
    {
        return FarmaadminMenuAccessCatalog::allKeys();
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<PartnerCompany, $this>
     */
    public function partnerCompany(): BelongsTo
    {
        return $this->belongsTo(PartnerCompany::class);
    }

    /**
     * Vinculación en `partner_company_users` (si existe).
     *
     * @return HasOne<PartnerCompanyUser, $this>
     */
    public function partnerCompanyUserLink(): HasOne
    {
        return $this->hasOne(PartnerCompanyUser::class);
    }

    public function roles(): HasMany
    {
        return $this->hasMany(Rol::class);
    }

    private function hasRole(string $role): bool
    {
        $roles = $this->getAttributeValue('roles');

        if (is_string($roles)) {
            $decoded = json_decode($roles, true);
            $roles = is_array($decoded) ? $decoded : [$roles];
        }

        if ($roles instanceof Collection) {
            $roles = $roles->all();
        }

        if (! is_array($roles)) {
            return false;
        }

        $normalizedRole = strtoupper(trim($role));
        $normalizedRoles = array_map(
            fn (mixed $item): string => is_string($item) ? strtoupper(trim($item)) : '',
            $roles
        );

        return in_array($normalizedRole, $normalizedRoles, true);
    }
}
