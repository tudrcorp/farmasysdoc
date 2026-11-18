<?php

namespace App\Services\WhatsappAgent;

use App\Models\WhatsappAgentSetting;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Support\WhatsappAgent\WhatsappAgentUrls;
use App\Support\WhatsappAgent\WhatsappGateway;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Le pasa a n8n el turno del asistente con todo el contexto de la conversación.
 * n8n piensa (Gemini + herramientas) y contesta llamando a la API `whatsapp-agent/v1`.
 */
final class WhatsappAgentTurnDispatcher
{
    private const TIMEOUT_SECONDS = 30;

    public function __construct(
        private readonly WhatsappConversationContextBuilder $contextBuilder,
        private readonly WhatsappMediaStorer $mediaStorer,
        private readonly WhatsappGateway $gateway,
        private readonly WhatsappMediaReader $mediaReader,
    ) {}

    /**
     * @throws RuntimeException Si n8n no recibió el turno (el job lo reintenta).
     */
    public function dispatch(WhatsappConversation $conversation, int $triggerMessageId): void
    {
        $setting = WhatsappAgentSetting::current();

        if (! $setting->agentCanRespond()) {
            return;
        }

        $this->gateway->markChatRead((string) $conversation->contact?->phone);
        $this->storePendingMedia($conversation);
        $this->mediaReader->readPending($conversation);

        $response = Http::timeout(self::TIMEOUT_SECONDS)
            ->acceptJson()
            ->withToken((string) $setting->agent_api_token)
            ->post((string) $setting->n8n_webhook_url, [
                'event' => 'agent_turn',
                'trigger_message_id' => $triggerMessageId,
                'api_base_url' => WhatsappAgentUrls::api(),
                'context' => $this->contextBuilder->build($conversation),
            ]);

        if (! $response->successful()) {
            throw new RuntimeException('n8n respondió con el código '.$response->status().': '.mb_substr((string) $response->body(), 0, 200));
        }

        $conversation->forceFill([
            'last_agent_turn_message_id' => max((int) $conversation->last_agent_turn_message_id, $triggerMessageId),
        ])->save();
    }

    private function storePendingMedia(WhatsappConversation $conversation): void
    {
        $conversation->messages()
            ->whereNotNull('media_url')
            ->whereNull('media_path')
            ->get()
            ->each(fn (WhatsappMessage $message): bool => $this->mediaStorer->store($message));
    }
}
