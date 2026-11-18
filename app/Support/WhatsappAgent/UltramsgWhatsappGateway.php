<?php

namespace App\Support\WhatsappAgent;

use App\Models\WhatsappAgentSetting;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Envío por Ultramsg con la instancia configurada en «Asistente WhatsApp» (no la de avisos de caja).
 */
final class UltramsgWhatsappGateway implements WhatsappGateway
{
    private const TIMEOUT_SECONDS = 20;

    private const MAX_MEDIA_BYTES = 16 * 1024 * 1024;

    public function isConfigured(): bool
    {
        return WhatsappAgentSetting::current()->hasUltramsgCredentials();
    }

    public function sendText(string $phone, string $body): WhatsappSendResult
    {
        return $this->post('messages/chat', $phone, ['body' => $body]);
    }

    public function sendImage(string $phone, string $contents, ?string $caption = null): WhatsappSendResult
    {
        return $this->post('messages/image', $phone, array_filter([
            'image' => base64_encode($contents),
            'caption' => filled($caption) ? mb_substr((string) $caption, 0, 1024) : null,
        ]), 60);
    }

    public function sendDocument(string $phone, string $contents, string $filename, ?string $caption = null): WhatsappSendResult
    {
        return $this->post('messages/document', $phone, array_filter([
            'document' => base64_encode($contents),
            'filename' => mb_substr($filename, 0, 120),
            'caption' => filled($caption) ? mb_substr((string) $caption, 0, 1024) : null,
        ]), 60);
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function post(string $endpoint, string $phone, array $payload, int $timeout = self::TIMEOUT_SECONDS): WhatsappSendResult
    {
        $setting = WhatsappAgentSetting::current();

        if (! $setting->hasUltramsgCredentials()) {
            return WhatsappSendResult::failed('La instancia de Ultramsg del asistente no está configurada.');
        }

        $to = WhatsappPhoneNumber::normalize($phone);

        if ($to === null) {
            return WhatsappSendResult::failed('Número de teléfono inválido.');
        }

        try {
            $response = Http::asForm()
                ->timeout($timeout)
                ->post(rtrim((string) $setting->ultramsg_instance_url, '/').'/'.$endpoint, [
                    'token' => (string) $setting->ultramsg_token,
                    'to' => '+'.$to,
                    ...$payload,
                ]);
        } catch (Throwable $exception) {
            Log::warning('Asistente WhatsApp: error de conexión con Ultramsg', ['endpoint' => $endpoint, 'error' => $exception->getMessage()]);

            return WhatsappSendResult::failed('No se pudo conectar con Ultramsg.');
        }

        $result = $response->json();

        if (! $response->successful() || ! is_array($result)) {
            Log::warning('Asistente WhatsApp: Ultramsg respondió con error', [
                'endpoint' => $endpoint,
                'status' => $response->status(),
                'body' => mb_substr((string) $response->body(), 0, 300),
            ]);

            return WhatsappSendResult::failed('Ultramsg respondió con el código '.$response->status().'.');
        }

        $error = $result['error'] ?? null;

        if (filled($error)) {
            $message = is_string($error) ? $error : (string) json_encode($error);
            Log::warning('Asistente WhatsApp: Ultramsg rechazó el envío', ['endpoint' => $endpoint, 'error' => $message]);

            return WhatsappSendResult::failed(mb_substr($message, 0, 255));
        }

        if (array_key_exists('sent', $result) && ! filter_var($result['sent'], FILTER_VALIDATE_BOOLEAN)) {
            return WhatsappSendResult::failed('Ultramsg no confirmó el envío.');
        }

        $id = $result['id'] ?? null;

        return new WhatsappSendResult(true, $id !== null ? (string) $id : null);
    }

    public function markChatRead(string $phone): bool
    {
        $setting = WhatsappAgentSetting::current();
        $chat = WhatsappPhoneNumber::normalize($phone);

        if (! $setting->hasUltramsgCredentials() || $chat === null) {
            return false;
        }

        try {
            return Http::asForm()
                ->timeout(10)
                ->post(rtrim((string) $setting->ultramsg_instance_url, '/').'/chats/read', [
                    'token' => (string) $setting->ultramsg_token,
                    'chatId' => $chat.'@c.us',
                ])
                ->successful();
        } catch (Throwable) {
            return false;
        }
    }

    /**
     * Ultramsg sirve los archivos desde S3 con un «Content-Encoding» que cURL no reconoce:
     * se piden sin compresión y sin decodificar.
     */
    public function downloadMedia(string $url): ?array
    {
        try {
            $response = Http::timeout(self::TIMEOUT_SECONDS)
                ->withHeaders(['Accept-Encoding' => 'identity'])
                ->withOptions(['decode_content' => false])
                ->get($url);
        } catch (Throwable $exception) {
            Log::warning('Asistente WhatsApp: no se pudo descargar el archivo del cliente', ['error' => $exception->getMessage()]);

            return null;
        }

        $contents = $response->successful() ? $response->body() : '';

        if ($contents === '' || strlen($contents) > self::MAX_MEDIA_BYTES) {
            return null;
        }

        $mime = $response->header('Content-Type');

        return [
            'contents' => $contents,
            'mime' => filled($mime) ? trim(explode(';', $mime)[0]) : null,
        ];
    }
}
