<?php

namespace App\Http\Controllers\Api\Delivery;

use App\Http\Controllers\Controller;
use App\Http\Requests\DeliveryLoginRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class DeliveryAuthController extends Controller
{
    /**
     * Autenticación para la app móvil de reparto (rol DELIVERY).
     * Emite un token Sanctum de tipo personal access.
     */
    public function login(DeliveryLoginRequest $request): JsonResponse
    {
        /** @var User|null $user */
        $user = User::query()
            ->where('email', $request->validated('email'))
            ->first();

        if (! $user || ! Hash::check($request->validated('password'), $user->password)) {
            return response()->json([
                'message' => __('Credenciales inválidas.'),
            ], 401);
        }

        if (! $user->isDeliveryUser()) {
            return response()->json([
                'message' => __('No autorizado para la app de reparto.'),
            ], 403);
        }

        $token = $user->createToken('farmadelivery')->plainTextToken;

        return response()->json([
            'token' => $token,
            'token_type' => 'Bearer',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
            ],
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        if ($user !== null) {
            $user->currentAccessToken()?->delete();
        }

        return response()->json(['message' => 'Sesión cerrada.']);
    }
}
