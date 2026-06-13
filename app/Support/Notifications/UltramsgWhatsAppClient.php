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

    public function sendImageMessage(string $to, string $image, string $caption): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        try {
            $response = Http::asForm()
                ->timeout((int) config('services.ultramsg.timeout', 15))
                ->post($this->imageMessagesEndpoint(), [
                    'token' => (string) config('services.ultramsg.token'),
                    'to' => $to,
                    'image' => $image,
                    'caption' => $caption,
                ]);

        } catch (Throwable $exception) {
            Log::warning('UltraMsg WhatsApp: error de conexión al enviar imagen', [
                'to' => $to,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }

        if (! $response->successful()) {
            Log::warning('UltraMsg WhatsApp: respuesta no exitosa al enviar imagen', [
                'to' => $to,
                'status' => $response->status(),
                'body' => mb_substr((string) $response->body(), 0, 300),
            ]);

            return false;
        }

        return true;
    }

    public function sendDocumentMessage(string $to, string $document, string $filename, ?string $caption = null): bool
    {
        if (! $this->isEnabled()) {
            return false;
        }

        $payload = [
            'token' => (string) config('services.ultramsg.token'),
            'to' => $to,
            'document' => $document,
            'filename' => $filename,
        ];

        if ($caption !== null && $caption !== '') {
            $payload['caption'] = $caption;
        }

        try {
            $response = Http::asForm()
                ->timeout((int) config('services.ultramsg.timeout', 15))
                ->post($this->documentMessagesEndpoint(), $payload);

        } catch (Throwable $exception) {
            Log::warning('UltraMsg WhatsApp: error de conexión al enviar documento', [
                'to' => $to,
                'filename' => $filename,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }

        if (! $response->successful()) {
            Log::warning('UltraMsg WhatsApp: respuesta no exitosa al enviar documento', [
                'to' => $to,
                'filename' => $filename,
                'status' => $response->status(),
                'body' => mb_substr((string) $response->body(), 0, 300),
            ]);

            return false;
        }

        return true;
    }

    public function resolvePhysicalCashBoxBannerImage(): ?string
    {
        $configuredUrl = trim((string) config('services.ultramsg.cash_box_banner_url', ''));
        if ($configuredUrl !== '') {
            return $configuredUrl;
        }

        $path = public_path('images/logos/farmadoc-ligth.png');
        if (! is_readable($path)) {
            return null;
        }

        $appUrl = rtrim((string) config('app.url'), '/');
        if (
            $appUrl !== ''
            && ! str_contains($appUrl, 'localhost')
            && ! str_contains($appUrl, '.test')
        ) {
            return $appUrl.'/images/logos/farmadoc-ligth.png';
        }

        $contents = file_get_contents($path);

        return is_string($contents) && $contents !== ''
            ? base64_encode($contents)
            : null;
    }

    private function messagesEndpoint(): string
    {
        $baseUrl = rtrim((string) config('services.ultramsg.base_url'), '/');

        return $baseUrl.'/messages/chat';
    }

    private function imageMessagesEndpoint(): string
    {
        $baseUrl = rtrim((string) config('services.ultramsg.base_url'), '/');

        return $baseUrl.'/messages/image';
    }

    private function documentMessagesEndpoint(): string
    {
        $baseUrl = rtrim((string) config('services.ultramsg.base_url'), '/');

        return $baseUrl.'/messages/document';
    }
}
