<?php

namespace App\Support\Partners;

use App\Enums\PartnerPanelPermission;
use App\Models\User;
use Filament\Facades\Filament;

/**
 * Atajos del panel de aliados (dale · Aliados): usuario autenticado, su compañía y sus permisos.
 */
final class PartnerPanel
{
    public const PASSWORD_BROKER = 'partner_panel';

    public const NAME = 'dale · Aliados';

    public static function user(): ?User
    {
        $user = Filament::auth()->user();

        return $user instanceof User && $user->isPartnerCompanyUser() ? $user : null;
    }

    public static function companyId(): ?int
    {
        $user = self::user();

        return $user === null ? null : (int) $user->partner_company_id;
    }

    public static function can(PartnerPanelPermission $permission): bool
    {
        return self::user()?->hasPartnerPermission($permission) ?? false;
    }

    public static function canAny(PartnerPanelPermission ...$permissions): bool
    {
        foreach ($permissions as $permission) {
            if (self::can($permission)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Porcentaje legible: enteros, o dos decimales cuando es menor a 1 (0,03 %) para que un consumo
     * pequeño no aparezca como 0 %.
     */
    public static function percent(float|int|null $value): string
    {
        $value = (float) ($value ?? 0);

        if ($value > 0 && $value < 1) {
            return number_format($value, 2, ',', '.').'%';
        }

        if ($value > 99 && $value < 100) {
            return number_format($value, 2, ',', '.').'%';
        }

        return number_format(round($value), 0, ',', '.').'%';
    }

    public static function accessLinkHours(): int
    {
        return max(1, intdiv((int) config('auth.passwords.'.self::PASSWORD_BROKER.'.expire', 4320), 60));
    }
}
