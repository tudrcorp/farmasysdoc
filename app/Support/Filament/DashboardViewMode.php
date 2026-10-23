<?php

namespace App\Support\Filament;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

/**
 * Vista activa del dashboard Farmaadmin (general o "Resumen de hoy"), persistida en sesión por usuario.
 */
final class DashboardViewMode
{
    public const string OVERVIEW = 'overview';

    public const string TODAY_SUMMARY = 'today_summary';

    private const SESSION_PREFIX = 'farmaadmin.dashboard.view_mode.';

    public static function canUseTodaySummary(): bool
    {
        $user = Auth::user();

        return $user instanceof User && ($user->isAdministrator() || $user->isManager());
    }

    public static function isTodaySummary(): bool
    {
        return self::canUseTodaySummary()
            && session(self::sessionKeyForCurrentUser()) === self::TODAY_SUMMARY;
    }

    public static function set(string $mode): void
    {
        if ($mode !== self::TODAY_SUMMARY || ! self::canUseTodaySummary()) {
            session()->forget(self::sessionKeyForCurrentUser());

            return;
        }

        session([self::sessionKeyForCurrentUser() => self::TODAY_SUMMARY]);
    }

    private static function sessionKeyForCurrentUser(): string
    {
        $userId = Auth::id();

        return self::SESSION_PREFIX.($userId ?? 'guest');
    }
}
