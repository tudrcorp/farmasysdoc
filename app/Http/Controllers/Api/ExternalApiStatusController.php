<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;

class ExternalApiStatusController extends Controller
{
    /**
     * Indica si la API de integración para aliados está disponible.
     *
     * No requiere autenticación: sirve para comprobar disponibilidad antes de llamadas con token.
     */
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'status' => 'active',
            'api' => 'external',
            'message' => 'La API de integración para aliados está operativa.',
            'checked_at' => now()->toIso8601String(),
            'app' => config('app.name'),
        ]);
    }
}
