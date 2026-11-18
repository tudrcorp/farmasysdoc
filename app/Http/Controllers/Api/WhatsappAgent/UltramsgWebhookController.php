<?php

namespace App\Http\Controllers\Api\WhatsappAgent;

use App\Http\Controllers\Controller;
use App\Models\WhatsappAgentSetting;
use App\Services\WhatsappAgent\WhatsappInboundMessageRecorder;
use App\Support\WhatsappAgent\UltramsgInboundMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Recibe los mensajes de Ultramsg. La URL lleva un secreto (Ultramsg no firma sus webhooks).
 * Siempre responde rápido: el turno del asistente corre en la cola.
 */
class UltramsgWebhookController extends Controller
{
    public function __invoke(Request $request, string $secret, WhatsappInboundMessageRecorder $recorder): JsonResponse
    {
        if (! hash_equals((string) WhatsappAgentSetting::current()->webhook_secret, $secret)) {
            return response()->json(['message' => 'No autorizado.'], Response::HTTP_NOT_FOUND);
        }

        $inbound = UltramsgInboundMessage::fromWebhookPayload($request->all());

        if ($inbound === null) {
            return response()->json(['status' => 'ignored']);
        }

        $message = $recorder->record($inbound);

        return response()->json([
            'status' => $message === null ? 'duplicate' : 'stored',
        ]);
    }
}
