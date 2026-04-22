<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Audit\AuditLogger;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

/**
 * Registra actividad HTTP en el panel Farmaadmin (navegación / interacción con la SPA),
 * con muestreo por ventana de tiempo para no saturar la tabla.
 */
final class AuditFarmaadminHttpActivity
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! Auth::check()) {
            return $response;
        }

        $user = Auth::user();
        if (! $user instanceof User) {
            return $response;
        }

        if (! $request->is('farmaadmin*')) {
            return $response;
        }

        if ($this->shouldSkipPath($request)) {
            return $response;
        }

        $window = max(5, (int) config('audit.http_log_window_seconds', 45));
        $routeName = $request->route()?->getName() ?? '';
        $fingerprint = sha1(
            $user->getKey().'|'.$request->method().'|'.$routeName.'|'.$request->path()
        );
        $cacheKey = 'audit:http:'.$fingerprint;

        if (Cache::has($cacheKey)) {
            return $response;
        }

        Cache::put($cacheKey, true, now()->addSeconds($window));

        $event = $request->isMethod('GET') ? 'page_view' : 'http_request';

        AuditLogger::record(
            event: $event,
            description: $event === 'page_view'
                ? 'Consulta / vista en el panel'
                : 'Petición HTTP en el panel',
            properties: [
                'method' => $request->method(),
                'path' => $request->path(),
                'route' => $routeName !== '' ? $routeName : null,
            ],
            request: $request,
            user: $user,
        );

        return $response;
    }

    private function shouldSkipPath(Request $request): bool
    {
        $path = $request->path();

        if (str_contains($path, 'livewire/livewire.min.js')
            || str_contains($path, 'fonts/')
            || str_contains($path, 'css/')
            || str_contains($path, 'js/')
            || str_contains($path, 'build/')
        ) {
            return true;
        }

        return false;
    }
}
