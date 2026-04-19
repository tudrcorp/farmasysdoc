<?php

namespace App\Support\Notifications;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class UltramsgWhatsAppClient
{
    public function isEnabled(): bool
    {
        return (bool) config('services.ultramsg.enabled', false)
            && filled(config('services.ultramsg.base_url'))
            && filled(config('services.ultramsg.token'));
    }

    public function sendTextMessage(string $to, string $body): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout((int) config('services.ultramsg.timeout', 15))
                ->post($this->messagesEndpoint(), [
                    'token' => (string) config('services.ultramsg.token'),
                    'to' => $to,
                    'body' => $body,
                ]);

        } catch (Throwable $exception) {
            Log::warning('UltraMsg WhatsApp: error de conexión', [
                'to' => $to,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }

        if (! $response->successful()) {
            Log::warning('UltraMsg WhatsApp: respuesta no exitosa', [
                'to' => $to,
                'status' => $response->status(),
                'body' => mb_substr((string) $response->body(), 0, 300),
            ]);

            return false;
        }

        return true;
    }

    private function messagesEndpoint(): string
    {
        $baseUrl = rtrim((string) config('services.ultramsg.base_url'), '/');

        return $baseUrl.'/messages/chat';
    }
}
