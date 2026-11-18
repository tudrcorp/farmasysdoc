<?php

namespace App\Jobs;

use App\Models\WhatsappMessage;
use App\Services\WhatsappAgent\WhatsappMediaReader;
use App\Services\WhatsappAgent\WhatsappMediaStorer;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Copia la foto o nota de voz del cliente al disco privado apenas llega. Si la conversación la atiende un analista,
 * la lee de una vez (récipe, comprobante…) para que la vea en la bandeja.
 */
class StoreWhatsappMessageMediaJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 150;

    /**
     * @var list<int>
     */
    public array $backoff = [5, 30];

    public function __construct(public int $messageId)
    {
        $this->afterCommit();
    }

    public function handle(WhatsappMediaStorer $storer, WhatsappMediaReader $reader): void
    {
        $message = WhatsappMessage::query()->with('conversation')->find($this->messageId);

        if (! $message instanceof WhatsappMessage || ! $storer->store($message)) {
            return;
        }

        $conversation = $message->conversation;

        if ($conversation !== null && ! $conversation->isHandledByAgent()) {
            $reader->readPending($conversation);
        }
    }
}
