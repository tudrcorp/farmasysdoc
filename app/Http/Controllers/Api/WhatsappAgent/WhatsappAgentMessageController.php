<?php

namespace App\Http\Controllers\Api\WhatsappAgent;

use App\Http\Controllers\Controller;
use App\Http\Requests\WhatsappAgent\StoreWhatsappTranscriptionRequest;
use App\Models\WhatsappMessage;
use App\Services\WhatsappAgent\WhatsappMediaStorer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Archivos del cliente (para que n8n los lea con Gemini) y transcripción de notas de voz.
 */
class WhatsappAgentMessageController extends Controller
{
    public function media(WhatsappMessage $message, WhatsappMediaStorer $storer): StreamedResponse|JsonResponse
    {
        if (! $message->hasStoredMedia() && ! $storer->store($message)) {
            return response()->json(['message' => 'El archivo no está disponible.'], Response::HTTP_NOT_FOUND);
        }

        $disk = Storage::disk(WhatsappMessage::MEDIA_DISK);

        if (! $disk->exists((string) $message->media_path)) {
            return response()->json(['message' => 'El archivo no está disponible.'], Response::HTTP_NOT_FOUND);
        }

        return $disk->response((string) $message->media_path, null, [
            'Content-Type' => $message->media_mime ?: 'application/octet-stream',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function transcription(StoreWhatsappTranscriptionRequest $request, WhatsappMessage $message): JsonResponse
    {
        $message->forceFill(['transcription' => (string) $request->validated('transcription')])->save();

        return response()->json(['saved' => true]);
    }
}
