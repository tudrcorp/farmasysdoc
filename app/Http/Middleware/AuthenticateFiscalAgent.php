<?php

namespace App\Http\Middleware;

use App\Models\FiscalPrinter;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Autentica al agente local de una máquina fiscal con su token Bearer (uno por máquina).
 */
class AuthenticateFiscalAgent
{
    /**
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

        $printer = FiscalPrinter::query()
            ->where('agent_token_hash', FiscalPrinter::hashToken($token))
            ->where('is_active', true)
            ->first();

        if (! $printer instanceof FiscalPrinter) {
            return response()->json([
                'message' => 'Token inválido o máquina fiscal inactiva.',
            ], Response::HTTP_UNAUTHORIZED);
        }

        $request->attributes->set('fiscalPrinter', $printer);

        return $next($request);
    }
}
