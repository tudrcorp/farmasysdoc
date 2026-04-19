<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Support\Filament\FarmaadminMenuAccessCatalog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureFarmaadminMenuAccess
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $route = $request->route();
        $routeName = is_object($route) && method_exists($route, 'getName')
            ? $route->getName()
            : null;

        if (! is_string($routeName) || ! str_starts_with($routeName, 'filament.farmaadmin.')) {
            return $next($request);
        }

        $user = $request->user();
        if (! $user instanceof User) {
            return $next($request);
        }

        if ($user->isAdministrator()) {
            return $next($request);
        }

        $menuKey = FarmaadminMenuAccessCatalog::resolveMenuKeyByRouteName($routeName);
        if ($menuKey === 'dashboard') {
            return $next($request);
        }

        if ($menuKey !== null && ! $user->canAccessFarmaadminMenuKey($menuKey)) {
            abort(403, 'No tienes permiso para acceder a este módulo.');
        }

        return $next($request);
    }
}
