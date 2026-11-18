<?php

namespace App\Support\WhatsappAgent;

/**
 * URLs públicas que usan Ultramsg y n8n para llegar a Farmasysdoc.
 *
 * Por defecto es APP_URL. En desarrollo (Herd) se define WHATSAPP_AGENT_PUBLIC_URL con la URL del túnel,
 * porque farmasysdoc.test solo existe en la máquina local.
 */
final class WhatsappAgentUrls
{
    public static function base(): string
    {
        $configured = trim((string) config('services.whatsapp_agent.public_url', ''));

        return rtrim($configured !== '' ? $configured : (string) config('app.url'), '/');
    }

    public static function api(string $path = ''): string
    {
        return self::base().'/api/whatsapp-agent/v1'.($path !== '' ? '/'.ltrim($path, '/') : '');
    }

    public static function ultramsgWebhook(string $secret): string
    {
        return self::base().'/api/webhooks/whatsapp/ultramsg/'.$secret;
    }
}
