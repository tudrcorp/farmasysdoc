<?php

namespace App\Services\WhatsappAgent;

use App\Enums\WhatsappConversationHandler;
use App\Enums\WhatsappConversationStatus;
use App\Enums\WhatsappMessageAuthor;
use App\Jobs\ProcessWhatsappAgentTurnJob;
use App\Jobs\StoreWhatsappMessageMediaJob;
use App\Models\WhatsappAgentSetting;
use App\Models\WhatsappContact;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Support\WhatsappAgent\UltramsgInboundMessage;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Guarda cada mensaje que llega por WhatsApp (aunque n8n esté caído) y decide si le toca responder al asistente.
 *
 * Los mensajes seguidos se agrupan: el turno del asistente se programa unos segundos después
 * y solo corre si no llegó otro mensaje mientras tanto.
 */
final class WhatsappInboundMessageRecorder
{
    public function __construct(private readonly WhatsappAnalystNotifier $notifier) {}

    /**
     * @return WhatsappMessage|null Null si el mensaje ya se había recibido (Ultramsg reintenta).
     */
    public function record(UltramsgInboundMessage $inbound): ?WhatsappMessage
    {
        if (WhatsappMessage::query()->where('provider_message_id', $inbound->providerMessageId)->exists()) {
            return null;
        }

        try {
            $message = DB::transaction(function () use ($inbound): WhatsappMessage {
                $contact = $this->resolveContact($inbound);
                $conversation = $this->resolveConversation($contact);

                $message = WhatsappMessage::query()->create([
                    'whatsapp_conversation_id' => $conversation->getKey(),
                    'author' => WhatsappMessageAuthor::Customer,
                    'type' => $inbound->type,
                    'body' => $inbound->body,
                    'provider_message_id' => $inbound->providerMessageId,
                    'media_url' => $inbound->mediaUrl,
                    'latitude' => $inbound->latitude,
                    'longitude' => $inbound->longitude,
                    'meta' => array_filter([
                        'quoted_body' => $inbound->quotedBody,
                        'provider_type' => $inbound->raw['type'] ?? null,
                    ]),
                ]);

                $conversation->forceFill([
                    'last_inbound_at' => now(),
                    ...($inbound->latitude !== null && $inbound->longitude !== null ? [
                        'delivery_latitude' => $inbound->latitude,
                        'delivery_longitude' => $inbound->longitude,
                    ] : []),
                ])->save();

                return $message;
            });
        } catch (QueryException $exception) {
            if (WhatsappMessage::query()->where('provider_message_id', $inbound->providerMessageId)->exists()) {
                return null;
            }

            throw $exception;
        }

        if ($message->media_url !== null) {
            StoreWhatsappMessageMediaJob::dispatch($message->getKey());
        }

        $this->afterRecorded($message);

        return $message;
    }

    private function resolveContact(UltramsgInboundMessage $inbound): WhatsappContact
    {
        $contact = WhatsappContact::query()->firstOrNew(['phone' => $inbound->phone]);

        if ($inbound->profileName !== null) {
            $contact->profile_name = mb_substr($inbound->profileName, 0, 160);
        }

        $contact->last_message_at = now();
        $contact->save();

        return $contact;
    }

    /**
     * Reutiliza la conversación abierta del contacto; si la última ya terminó, abre una nueva.
     */
    private function resolveConversation(WhatsappContact $contact): WhatsappConversation
    {
        $open = WhatsappConversation::query()
            ->where('whatsapp_contact_id', $contact->getKey())
            ->open()
            ->latest('id')
            ->lockForUpdate()
            ->first();

        if ($open instanceof WhatsappConversation) {
            return $open;
        }

        return WhatsappConversation::query()->create([
            'whatsapp_contact_id' => $contact->getKey(),
            'status' => WhatsappConversationStatus::Bot,
            'handled_by' => WhatsappConversationHandler::Agent,
            'state' => ['stage' => 'saludo'],
        ]);
    }

    private function afterRecorded(WhatsappMessage $message): void
    {
        $conversation = $message->conversation;
        $contact = $conversation?->contact;

        if (! $conversation instanceof WhatsappConversation || ! $contact instanceof WhatsappContact) {
            return;
        }

        $setting = WhatsappAgentSetting::current();

        if (! $conversation->isHandledByAgent()
            && ! ($setting->agentCanRespond()
                && $setting->allowsAgentFor($contact->phone)
                && app(WhatsappConversationWorkflow::class)->returnToAgentIfAnalystIdle($conversation, (int) $setting->analyst_idle_minutes))) {
            $this->notifier->customerWroteToAnalyst($conversation, $message);

            return;
        }

        if (! $setting->agentCanRespond() || ! $setting->allowsAgentFor($contact->phone)) {
            return;
        }

        ProcessWhatsappAgentTurnJob::dispatch($conversation->getKey(), $message->getKey())
            ->delay(now()->addSeconds(max(0, (int) $setting->message_grouping_seconds)));
    }
}
