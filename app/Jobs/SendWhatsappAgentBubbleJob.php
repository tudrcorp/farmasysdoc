<?php

namespace App\Jobs;

use App\Enums\WhatsappMessageAuthor;
use App\Models\WhatsappConversation;
use App\Services\WhatsappAgent\WhatsappOutboundMessenger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Envía una parte (burbuja) de la respuesta del asistente después de una pausa, como una persona que sigue escribiendo.
 * Si mientras tanto un analista tomó la conversación, no se envía.
 */
class SendWhatsappAgentBubbleJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(
        public int $conversationId,
        public string $body,
    ) {
        $this->afterCommit();
    }

    public function handle(WhatsappOutboundMessenger $messenger): void
    {
        $conversation = WhatsappConversation::query()->with('contact')->find($this->conversationId);

        if (! $conversation instanceof WhatsappConversation || ! $conversation->isOpen() || ! $conversation->isHandledByAgent()) {
            return;
        }

        $messenger->send($conversation, $this->body, WhatsappMessageAuthor::Agent);
    }
}
