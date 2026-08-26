<?php

namespace App\Support\Notifications;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

final class UltramsgWhatsAppClient
{
    private const MEDIA_CAPTION_MAX_LENGTH = 1024;

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

        return $this->wasAccepted($response, 'mensaje de texto', ['to' => $to]);
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
                    'caption' => $this->limitMediaCaption($caption),
                ]);

        } catch (Throwable $exception) {
            Log::warning('UltraMsg WhatsApp: error de conexión al enviar imagen', [
                'to' => $to,
                'error' => $exception->getMessage(),
            ]);

            return false;
        }

        return $this->wasAccepted($response, 'imagen', ['to' => $to]);
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
            $payload['caption'] = $this->limitMediaCaption($caption);
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

        return $this->wasAccepted($response, 'documento', [
            'to' => $to,
            'filename' => $filename,
        ]);
    }

    public function resolvePhysicalCashBoxBannerImage(): ?string
    {
        return $this->resolveFarmadocLogoImage();
    }

    /**
     * Logo Farmadoc para encabezado de notificaciones WhatsApp (URL pública o base64).
     */
    public function resolveFarmadocLogoImage(): ?string
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

    /**
     * @param  array<string, mixed>  $context
     */
    private function wasAccepted(Response $response, string $kind, array $context): bool
    {
        $snippet = mb_substr((string) $response->body(), 0, 300);
        $payload = $response->json();

        if (! $response->successful()) {
            Log::warning('UltraMsg WhatsApp: respuesta no exitosa al enviar '.$kind, [
                ...$context,
                'status' => $response->status(),
                'body' => $snippet,
            ]);

            return false;
        }

        if (! is_array($payload)) {
            return true;
        }

        $error = $payload['error'] ?? null;
        if (is_string($error) && trim($error) !== '') {
            Log::warning('UltraMsg WhatsApp: API rechazó el envío de '.$kind, [
                ...$context,
                'status' => $response->status(),
                'error' => $error,
                'body' => $snippet,
            ]);

            return false;
        }

        if (array_key_exists('sent', $payload) && ! filter_var($payload['sent'], FILTER_VALIDATE_BOOLEAN)) {
            Log::warning('UltraMsg WhatsApp: API no confirmó el envío de '.$kind, [
                ...$context,
                'status' => $response->status(),
                'body' => $snippet,
            ]);

            return false;
        }

        return true;
    }

    private function limitMediaCaption(string $caption): string
    {
        if (mb_strlen($caption) <= self::MEDIA_CAPTION_MAX_LENGTH) {
            return $caption;
        }

        return mb_substr($caption, 0, self::MEDIA_CAPTION_MAX_LENGTH - 1).'…';
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
