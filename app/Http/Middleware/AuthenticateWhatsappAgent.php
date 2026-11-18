<?php

namespace App\Http\Middleware;

use App\Models\WhatsappAgentSetting;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica a n8n con el token del asistente (Bearer) configurado en «Asistente WhatsApp».
 * Fuerza respuestas JSON (también en errores de validación) para que n8n las lea como herramienta.
 */
class AuthenticateWhatsappAgent
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $request->headers->set('Accept', 'application/json');

        if (! WhatsappAgentSetting::current()->agentApiTokenMatches($request->bearerToken())) {
            return response()->json([
                'message' => 'Token del asistente inválido o ausente.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        return $next($request);
    }
}
