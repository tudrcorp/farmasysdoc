<?php

namespace App\Jobs;

use App\Enums\WhatsappMessageAuthor;
use App\Models\WhatsappMessage;
use App\Services\WhatsappAgent\WhatsappOutboundMessenger;
use App\Support\WhatsappAgent\WhatsappGateway;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Envía por WhatsApp un mensaje que el analista escribió en la bandeja (ya visible con ⏱).
 */
class SendWhatsappMessageJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 40;

    public function __construct(public int $messageId)
    {
        $this->afterCommit();
    }

    public function handle(WhatsappOutboundMessenger $messenger, WhatsappGateway $gateway): void
    {
        $message = WhatsappMessage::query()->find($this->messageId);

        if (! $message instanceof WhatsappMessage || ! $message->isPendingDelivery()) {
            return;
        }

        $messenger->deliver($message);

        if ($message->author === WhatsappMessageAuthor::Analyst && ! $message->delivery_failed) {
            $gateway->markChatRead((string) $message->conversation?->contact?->phone);
        }
    }
}
