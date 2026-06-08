<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Cash\CashierShiftLock;
use Closure;
use Filament\Facades\Filament;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureCashierShiftNotLocked
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && CashierShiftLock::isLocked($user)) {
            Auth::guard(Filament::getAuthGuard())->logout();

            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()
                ->to(Filament::getLoginUrl())
                ->with('cashier_shift_locked_message', CashierShiftLock::loginBlockedMessage($user));
        }

        return $next($request);
    }
}
