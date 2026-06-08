<?php

namespace App\Support\Cash;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Carbon;

/**
 * Bloqueo de ingreso para cajeros tras cerrar la caja física hasta el próximo
 * desbloqueo diario (6:00 AM por defecto) o hasta que un administrador lo habilite.
 */
final class CashierShiftLock
{
    public static function lockAfterShiftClose(User $user): void
    {
        if (! $user->isCashier()) {
            return;
        }

        $user->forceFill([
            'cashier_shift_locked_until' => self::nextDailyUnlockFrom(),
        ])->save();
    }

    /**
     * @deprecated Use {@see lockAfterShiftClose()}.
     */
    public static function lockUntilNextDay(User $user): void
    {
        self::lockAfterShiftClose($user);
    }

    public static function grantManualAccess(User $cashier, User $administrator): void
    {
        if (! $cashier->isCashier()) {
            return;
        }

        if (! $administrator->isAdministrator()) {
            abort(403);
        }

        $previousLockUntil = $cashier->cashier_shift_locked_until;

        self::clear($cashier);

        AuditLogger::record(
            'cashier_shift_access_granted',
            'Administración · Acceso de cajero habilitado manualmente · '.$cashier->name,
            User::class,
            $cashier->id,
            $cashier->email,
            [
                'module' => 'cashier_shift',
                'cashier_id' => $cashier->id,
                'cashier_email' => $cashier->email,
                'administrator_id' => $administrator->id,
                'administrator_email' => $administrator->email,
                'previous_locked_until' => $previousLockUntil?->toIso8601String(),
            ],
        );
    }

    public static function clear(User $user): void
    {
        if ($user->cashier_shift_locked_until === null) {
            return;
        }

        $user->forceFill([
            'cashier_shift_locked_until' => null,
        ])->save();
    }

    public static function isLocked(User $user): bool
    {
        if (! $user->isCashier()) {
            return false;
        }

        $lockedUntil = $user->cashier_shift_locked_until;

        if ($lockedUntil === null) {
            return false;
        }

        return Carbon::now()->lt($lockedUntil);
    }

    public static function loginBlockedMessage(?User $user = null): string
    {
        if ($user instanceof User && filled(self::lockedUntilLabel($user))) {
            return 'Su turno de caja física ya fue cerrado. Podrá ingresar nuevamente a las '
                .self::lockedUntilLabel($user)
                .' o cuando un administrador habilite su acceso.';
        }

        return 'Su turno de caja física ya fue cerrado. Podrá ingresar nuevamente a las 6:00 AM o cuando un administrador habilite su acceso.';
    }

    public static function lockedUntilLabel(User $user): ?string
    {
        if ($user->cashier_shift_locked_until === null) {
            return null;
        }

        return $user->cashier_shift_locked_until
            ->timezone(self::timezone())
            ->format('d/m/Y H:i');
    }

    public static function nextDailyUnlockFrom(?Carbon $from = null): Carbon
    {
        $from = ($from ?? Carbon::now())->timezone(self::timezone());
        $hour = max(0, min(23, (int) config('cashier_shift.daily_unlock_hour', 6)));
        $minute = max(0, min(59, (int) config('cashier_shift.daily_unlock_minute', 0)));

        $candidate = $from->copy()->setTime($hour, $minute, 0);

        if ($from->gte($candidate)) {
            $candidate->addDay();
        }

        return $candidate;
    }

    public static function dailyUnlockTimeLabel(): string
    {
        $hour = max(0, min(23, (int) config('cashier_shift.daily_unlock_hour', 6)));
        $minute = max(0, min(59, (int) config('cashier_shift.daily_unlock_minute', 0)));

        return sprintf('%02d:%02d', $hour, $minute);
    }

    private static function timezone(): string
    {
        return (string) config('app.timezone', 'UTC');
    }
}
