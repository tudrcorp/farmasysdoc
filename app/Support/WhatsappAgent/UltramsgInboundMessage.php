<?php

namespace App\Support\WhatsappAgent;

use App\Enums\WhatsappMessageType;

/**
 * Mensaje entrante ya interpretado desde el webhook de Ultramsg.
 */
final readonly class UltramsgInboundMessage
{
    /**
     * @param  array<string, mixed>  $raw
     */
    public function __construct(
        public string $providerMessageId,
        public string $phone,
        public ?string $profileName,
        public WhatsappMessageType $type,
        public ?string $body,
        public ?string $mediaUrl,
        public ?float $latitude,
        public ?float $longitude,
        public ?string $quotedBody,
        public array $raw,
    ) {}

    /**
     * Interpreta el JSON del webhook. Devuelve null si no es un mensaje de un cliente
     * (eventos de envío propio, grupos, estados, acuses de lectura).
     *
     * @param  array<string, mixed>  $payload
     */
    public static function fromWebhookPayload(array $payload): ?self
    {
        $event = (string) ($payload['event_type'] ?? '');
        $data = $payload['data'] ?? null;

        if ($event !== 'message_received' || ! is_array($data)) {
            return null;
        }

        if (filter_var($data['fromMe'] ?? false, FILTER_VALIDATE_BOOLEAN)) {
            return null;
        }

        $from = (string) ($data['from'] ?? '');

        if ($from === '' || WhatsappPhoneNumber::isGroupChat($from) || str_contains($from, 'status@broadcast')) {
            return null;
        }

        $phone = WhatsappPhoneNumber::normalize($from);
        $providerId = trim((string) ($data['id'] ?? ''));

        if ($phone === null || $providerId === '') {
            return null;
        }

        $type = WhatsappMessageType::fromUltramsg((string) ($data['type'] ?? 'chat'));
        $body = self::cleanText($data['body'] ?? null);
        $caption = self::cleanText($data['caption'] ?? null);
        $media = self::cleanText($data['media'] ?? null);

        [$latitude, $longitude] = self::coordinates($data, $type, $body);

        if ($type === WhatsappMessageType::Location) {
            $body = self::cleanText($data['location']['address'] ?? null) ?? $caption;
        } elseif ($type->hasMedia()) {
            $body = $caption ?? ($body !== null && ! str_starts_with($body, 'http') && mb_strlen($body) < 1000 ? $body : null);
            $media ??= self::cleanText(is_string($data['body'] ?? null) && str_starts_with((string) $data['body'], 'http') ? $data['body'] : null);
        }

        $quoted = $data['quotedMsg'] ?? null;

        return new self(
            providerMessageId: mb_substr($providerId, 0, 191),
            phone: $phone,
            profileName: self::cleanText($data['pushname'] ?? null),
            type: $type,
            body: $body,
            mediaUrl: $media !== null && str_starts_with($media, 'http') ? $media : null,
            latitude: $latitude,
            longitude: $longitude,
            quotedBody: is_array($quoted) ? self::cleanText($quoted['body'] ?? null) : null,
            raw: $data,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: float|null, 1: float|null}
     */
    private static function coordinates(array $data, WhatsappMessageType $type, ?string $body): array
    {
        if ($type !== WhatsappMessageType::Location) {
            return [null, null];
        }

        $location = is_array($data['location'] ?? null) ? $data['location'] : [];
        $latitude = $location['latitude'] ?? $location['lat'] ?? $data['lat'] ?? null;
        $longitude = $location['longitude'] ?? $location['lng'] ?? $data['lng'] ?? null;

        if (is_numeric($latitude) && is_numeric($longitude)) {
            return [(float) $latitude, (float) $longitude];
        }

        if ($body !== null && preg_match('/(-?\d{1,2}\.\d+)\s*[,;]\s*(-?\d{1,3}\.\d+)/', $body, $matches) === 1) {
            return [(float) $matches[1], (float) $matches[2]];
        }

        return [null, null];
    }

    private static function cleanText(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }
}
