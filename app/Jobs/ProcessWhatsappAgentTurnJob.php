<?php

namespace App\Jobs;

use App\Models\WhatsappConversation;
use App\Services\WhatsappAgent\WhatsappAgentTurnDispatcher;
use App\Services\WhatsappAgent\WhatsappConversationEscalator;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Turno del asistente. Se programa unos segundos después de cada mensaje del cliente y solo corre
 * si ese sigue siendo el último mensaje (así varios mensajes seguidos reciben una sola respuesta).
 */
class ProcessWhatsappAgentTurnJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 150;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 20, 30, 60];

    public function __construct(
        public int $conversationId,
        public int $triggerMessageId,
    ) {
        $this->afterCommit();
    }

    /**
     * @return list<object>
     */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('whatsapp-agent-turn:'.$this->conversationId))
                ->releaseAfter(5)
                ->expireAfter(180),
        ];
    }

    public function handle(WhatsappAgentTurnDispatcher $dispatcher): void
    {
        $conversation = WhatsappConversation::query()->with('contact')->find($this->conversationId);

        if (! $conversation instanceof WhatsappConversation || ! $conversation->isOpen() || ! $conversation->isHandledByAgent()) {
            return;
        }

        if ($conversation->latestInboundMessageId() !== $this->triggerMessageId) {
            return;
        }

        if ((int) $conversation->last_agent_turn_message_id >= $this->triggerMessageId) {
            return;
        }

        $dispatcher->dispatch($conversation, $this->triggerMessageId);
    }

    public function failed(?Throwable $exception): void
    {
        Log::error('Asistente WhatsApp: n8n no respondió el turno', [
            'conversation_id' => $this->conversationId,
            'error' => $exception?->getMessage(),
        ]);

        $conversation = WhatsappConversation::query()->find($this->conversationId);

        if ($conversation instanceof WhatsappConversation) {
            app(WhatsappConversationEscalator::class)->escalate(
                $conversation,
                'El asistente no pudo responder por una falla técnica. Atienda al cliente directamente.',
            );
        }
    }
}
