<?php

namespace App\Http\Middleware;

use App\Models\ApiClient;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateApiClient
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();

        if (blank($token)) {
            return response()->json([
                'message' => 'Token Bearer requerido.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $apiClient = ApiClient::query()
            ->where('token_hash', ApiClient::hashToken($token))
            ->where('is_active', true)
            ->first();

        if ($apiClient === null) {
            return response()->json([
                'message' => 'Token inválido o inactivo.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        if (is_array($apiClient->allowed_ips) && $apiClient->allowed_ips !== []) {
            $requestIp = $request->ip();

            if (! in_array($requestIp, $apiClient->allowed_ips, true)) {
                return response()->json([
                    'message' => 'IP no autorizada para este cliente API.',
                ], Response::HTTP_FORBIDDEN);
            }
        }

        $apiClient->forceFill([
            'last_used_at' => now(),
        ])->save();

        $request->attributes->set('apiClient', $apiClient);

        return $next($request);
    }
}
