<?php

namespace App\Services\WhatsappAgent;

use App\Enums\WhatsappMessageAuthor;
use App\Enums\WhatsappMessageType;
use App\Jobs\SendWhatsappAgentBubbleJob;
use App\Jobs\SendWhatsappMessageJob;
use App\Models\User;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Support\WhatsappAgent\WhatsappGateway;
use App\Support\WhatsappAgent\WhatsappSendResult;
use Illuminate\Support\Facades\Storage;

/**
 * Envía mensajes al cliente (del asistente o de un analista) y los deja en el historial con su estado de entrega:
 * pendiente (⏱), enviado (✓) o fallido (⚠).
 */
final class WhatsappOutboundMessenger
{
    public const MAX_BUBBLES = 3;

    public function __construct(private readonly WhatsappGateway $gateway) {}

    /**
     * Para el analista: el mensaje aparece al instante en la bandeja y sale por la cola.
     */
    public function queue(WhatsappConversation $conversation, string $body, WhatsappMessageAuthor $author, ?User $user = null): WhatsappMessage
    {
        $message = $this->record($conversation, $body, $author, $user);

        SendWhatsappMessageJob::dispatch((int) $message->getKey());

        return $message;
    }

    /**
     * Foto o archivo que el analista envía desde la bandeja. Se guarda en el disco privado y sale por la cola.
     */
    public function queueAttachment(
        WhatsappConversation $conversation,
        string $storedPath,
        string $mime,
        string $originalName,
        ?string $caption,
        User $user,
    ): WhatsappMessage {
        $message = WhatsappMessage::query()->create([
            'whatsapp_conversation_id' => $conversation->getKey(),
            'author' => WhatsappMessageAuthor::Analyst,
            'type' => str_starts_with($mime, 'image/') ? WhatsappMessageType::Image : WhatsappMessageType::Document,
            'body' => filled($caption) ? trim((string) $caption) : null,
            'media_path' => $storedPath,
            'media_mime' => $mime,
            'sent_by_user_id' => $user->getKey(),
            'delivery_status' => WhatsappMessage::STATUS_PENDING,
            'meta' => ['filename' => $originalName],
        ]);

        $conversation->forceFill(['last_outbound_at' => now()])->save();

        SendWhatsappMessageJob::dispatch((int) $message->getKey());

        return $message;
    }

    /**
     * Envío inmediato (lo usa el asistente, que ya está en segundo plano).
     */
    public function send(WhatsappConversation $conversation, string $body, WhatsappMessageAuthor $author, ?User $user = null): WhatsappMessage
    {
        return $this->deliver($this->record($conversation, $body, $author, $user));
    }

    public function deliver(WhatsappMessage $message): WhatsappMessage
    {
        $conversation = $message->conversation()->with('contact')->first();
        $phone = (string) $conversation?->contact?->phone;
        $disk = Storage::disk(WhatsappMessage::MEDIA_DISK);

        $result = match (true) {
            $message->hasStoredMedia() && ! $disk->exists((string) $message->media_path) => WhatsappSendResult::failed('El archivo ya no está disponible.'),
            $message->type === WhatsappMessageType::Image && $message->hasStoredMedia() => $this->gateway->sendImage($phone, (string) $disk->get((string) $message->media_path), $message->body),
            $message->type === WhatsappMessageType::Document && $message->hasStoredMedia() => $this->gateway->sendDocument(
                $phone,
                (string) $disk->get((string) $message->media_path),
                (string) ($message->meta['filename'] ?? basename((string) $message->media_path)),
                $message->body,
            ),
            default => $this->gateway->sendText($phone, (string) $message->body),
        };

        $message->forceFill([
            'delivery_status' => $result->sent ? WhatsappMessage::STATUS_SENT : WhatsappMessage::STATUS_FAILED,
            'delivery_failed' => ! $result->sent,
            'meta' => array_filter([
                ...($message->meta ?? []),
                'provider_id' => $result->providerMessageId,
                'error' => $result->error,
            ]),
        ])->save();

        return $message;
    }

    /**
     * Vuelve a intentar un mensaje que no salió.
     */
    public function retry(WhatsappMessage $message): void
    {
        if ($message->delivery_status !== WhatsappMessage::STATUS_FAILED) {
            return;
        }

        $message->forceFill(['delivery_status' => WhatsappMessage::STATUS_PENDING, 'delivery_failed' => false])->save();

        SendWhatsappMessageJob::dispatch((int) $message->getKey());
    }

    /**
     * Respuesta del asistente en burbujas: cada párrafo (separado por una línea en blanco) es un mensaje.
     * El primero sale de inmediato y los siguientes con una pausa según su largo, como una persona escribiendo.
     */
    public function sendAsBubbles(WhatsappConversation $conversation, string $body): WhatsappMessage
    {
        $bubbles = self::splitIntoBubbles($body);
        $first = $this->send($conversation, array_shift($bubbles) ?? $body, WhatsappMessageAuthor::Agent);

        if ($first->delivery_failed) {
            return $first;
        }

        $delay = 0;
        foreach ($bubbles as $bubble) {
            $delay += self::typingSeconds($bubble);
            SendWhatsappAgentBubbleJob::dispatch((int) $conversation->getKey(), $bubble)->delay(now()->addSeconds($delay));
        }

        return $first;
    }

    /**
     * @return list<string>
     */
    public static function splitIntoBubbles(string $body): array
    {
        $parts = array_values(array_filter(
            array_map('trim', preg_split('/\n\s*\n/u', trim($body)) ?: []),
            static fn (string $part): bool => $part !== '',
        ));

        if ($parts === []) {
            return [trim($body)];
        }

        if (count($parts) > self::MAX_BUBBLES) {
            $head = array_slice($parts, 0, self::MAX_BUBBLES - 1);
            $head[] = implode("\n\n", array_slice($parts, self::MAX_BUBBLES - 1));
            $parts = $head;
        }

        return $parts;
    }

    /**
     * Segundos que tardaría una persona en escribir el texto (entre 2 y 7).
     */
    public static function typingSeconds(string $text): int
    {
        return (int) min(7, max(2, round(mb_strlen($text) / 22)));
    }

    private function record(WhatsappConversation $conversation, string $body, WhatsappMessageAuthor $author, ?User $user): WhatsappMessage
    {
        $message = WhatsappMessage::query()->create([
            'whatsapp_conversation_id' => $conversation->getKey(),
            'author' => $author,
            'type' => WhatsappMessageType::Text,
            'body' => trim($body),
            'sent_by_user_id' => $user?->getKey(),
            'delivery_status' => WhatsappMessage::STATUS_PENDING,
        ]);

        $conversation->forceFill(['last_outbound_at' => now()])->save();

        return $message;
    }
}
