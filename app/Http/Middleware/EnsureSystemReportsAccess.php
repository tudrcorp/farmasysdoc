<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Reportes del sistema: solo administradores o rol GERENCIA.
 */
final class EnsureSystemReportsAccess
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            abort(403);
        }

        if (! $user->isAdministrator() && ! $user->hasGerenciaRole()) {
            abort(403, 'Solo administración o gerencia pueden descargar reportes del sistema.');
        }

        return $next($request);
    }
}
