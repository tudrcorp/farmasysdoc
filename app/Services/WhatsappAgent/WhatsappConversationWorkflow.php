<?php

namespace App\Services\WhatsappAgent;

use App\Enums\WhatsappConversationHandler;
use App\Enums\WhatsappConversationStatus;
use App\Enums\WhatsappFulfillmentType;
use App\Enums\WhatsappMessageAuthor;
use App\Enums\WhatsappMessageType;
use App\Models\PrescriptionReading;
use App\Models\User;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use App\Services\Audit\AuditLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Lo que hace el analista desde la bandeja: tomar, responder, devolver al asistente, cerrar,
 * corregir el carrito y los datos de entrega, y validar el récipe.
 */
final class WhatsappConversationWorkflow
{
    public function __construct(
        private readonly WhatsappOutboundMessenger $messenger,
        private readonly WhatsappAnalystNotifier $notifier,
    ) {}

    /**
     * El analista toma la conversación: desde ahora el asistente deja de responder.
     */
    public function take(WhatsappConversation $conversation, User $analyst): void
    {
        $this->assertOpen($conversation);

        $conversation->forceFill([
            'handled_by' => WhatsappConversationHandler::Analyst,
            'assigned_user_id' => $analyst->getKey(),
            'status' => WhatsappConversationStatus::InProgress,
        ])->save();

        $this->systemNote($conversation, $analyst->name.' tomó la conversación.');
        $this->audit('whatsapp_conversation_taken', 'WhatsApp · Conversación tomada por '.$analyst->name, $conversation, $analyst);
    }

    /**
     * Pasa la conversación a otro analista (queda en «En gestión» a su nombre).
     */
    public function assignTo(WhatsappConversation $conversation, User $assignee, User $actor): void
    {
        $this->assertOpen($conversation);

        $conversation->forceFill([
            'handled_by' => WhatsappConversationHandler::Analyst,
            'assigned_user_id' => $assignee->getKey(),
            'status' => WhatsappConversationStatus::InProgress,
        ])->save();

        $this->systemNote($conversation, $actor->name.' asignó la conversación a '.$assignee->name.'.');
        $this->audit('whatsapp_conversation_assigned', 'WhatsApp · Conversación asignada a '.$assignee->name, $conversation, $actor);
        $this->notifier->conversationAssigned($conversation, $assignee, $actor);
    }

    public function reply(WhatsappConversation $conversation, User $analyst, string $body): WhatsappMessage
    {
        $this->assertOpen($conversation);

        if ($conversation->isHandledByAgent() || (int) $conversation->assigned_user_id !== (int) $analyst->getKey()) {
            $this->take($conversation, $analyst);
        }

        return $this->messenger->queue($conversation, $body, WhatsappMessageAuthor::Analyst, $analyst);
    }

    public function replyWithAttachment(
        WhatsappConversation $conversation,
        User $analyst,
        string $storedPath,
        string $mime,
        string $originalName,
        ?string $caption,
    ): WhatsappMessage {
        $this->assertOpen($conversation);

        if ($conversation->isHandledByAgent() || (int) $conversation->assigned_user_id !== (int) $analyst->getKey()) {
            $this->take($conversation, $analyst);
        }

        return $this->messenger->queueAttachment($conversation, $storedPath, $mime, $originalName, $caption, $analyst);
    }

    /**
     * Devuelve la conversación al asistente con una nota de lo que hizo el analista,
     * para que retome sin repetir preguntas ni responder mensajes ya atendidos.
     */
    public function returnToAgent(WhatsappConversation $conversation, User $analyst, ?string $note = null): void
    {
        $this->assertOpen($conversation);

        $conversation->forceFill([
            'handled_by' => WhatsappConversationHandler::Agent,
            'status' => WhatsappConversationStatus::Bot,
            'escalated_at' => null,
            'escalated_off_hours' => false,
            'last_agent_turn_message_id' => $conversation->latestInboundMessageId(),
            'state' => [
                ...($conversation->state ?? []),
                'analyst_note' => filled($note) ? trim((string) $note) : null,
            ],
        ])->save();

        $this->systemNote($conversation, $analyst->name.' devolvió la conversación al asistente.'.(filled($note) ? ' Nota: '.trim((string) $note) : ''));
        $this->audit('whatsapp_conversation_returned', 'WhatsApp · Conversación devuelta al asistente', $conversation, $analyst);
    }

    /**
     * Minutos desde la última vez que un analista escribió o tomó la conversación.
     */
    public function minutesSinceAnalystActivity(WhatsappConversation $conversation): ?int
    {
        $last = $conversation->messages()
            ->whereIn('author', [WhatsappMessageAuthor::Analyst->value, WhatsappMessageAuthor::System->value])
            ->reorder('id', 'desc')
            ->value('created_at');

        return $last !== null ? (int) Carbon::parse($last)->diffInMinutes(now()) : null;
    }

    /**
     * El cliente escribió y el analista lleva demasiado tiempo sin responder: el asistente retoma la conversación
     * para que el cliente no quede sin respuesta. Devuelve true si la devolvió.
     */
    public function returnToAgentIfAnalystIdle(WhatsappConversation $conversation, int $idleMinutes): bool
    {
        if ($idleMinutes <= 0 || ! $conversation->isOpen() || $conversation->isHandledByAgent()) {
            return false;
        }

        $minutes = $this->minutesSinceAnalystActivity($conversation);

        if ($minutes !== null && $minutes < $idleMinutes) {
            return false;
        }

        $analyst = $conversation->assignedUser;

        $conversation->forceFill([
            'handled_by' => WhatsappConversationHandler::Agent,
            'status' => WhatsappConversationStatus::Bot,
            'escalated_at' => null,
            'escalated_off_hours' => false,
        ])->save();

        $this->systemNote($conversation, 'Sin respuesta del analista en '.$idleMinutes.' min: el asistente retomó la conversación.');

        if ($analyst instanceof User) {
            $this->notifier->conversationReturnedForIdle($conversation, $analyst, $idleMinutes);
        }

        return true;
    }

    public function close(WhatsappConversation $conversation, User $analyst, string $reason): void
    {
        $this->assertOpen($conversation);

        $conversation->forceFill([
            'status' => WhatsappConversationStatus::Closed,
            'closed_at' => now(),
            'closed_by_user_id' => $analyst->getKey(),
        ])->save();

        $this->systemNote($conversation, $analyst->name.' cerró la conversación: '.trim($reason));
        $this->audit('whatsapp_conversation_closed', 'WhatsApp · Conversación cerrada: '.mb_substr(trim($reason), 0, 120), $conversation, $analyst);
    }

    /**
     * @param  list<array{product_id?: int|string|null, requested_name?: string|null, quantity?: int|float|string|null, notes?: string|null}>  $items
     */
    public function syncItems(WhatsappConversation $conversation, array $items): void
    {
        $this->assertOpen($conversation);

        DB::transaction(function () use ($conversation, $items): void {
            $conversation->items()->delete();

            foreach ($items as $row) {
                $quantity = (float) ($row['quantity'] ?? 0);

                if ($quantity <= 0) {
                    continue;
                }

                $conversation->items()->create([
                    'product_id' => filled($row['product_id'] ?? null) ? (int) $row['product_id'] : null,
                    'requested_name' => mb_substr(trim((string) ($row['requested_name'] ?? '')) ?: 'Producto', 0, 255),
                    'quantity' => $quantity,
                    'notes' => filled($row['notes'] ?? null) ? mb_substr(trim((string) $row['notes']), 0, 500) : null,
                ]);
            }
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function updateDelivery(WhatsappConversation $conversation, array $data): void
    {
        $this->assertOpen($conversation);

        $conversation->forceFill([
            'fulfillment_type' => filled($data['fulfillment_type'] ?? null) ? WhatsappFulfillmentType::from((string) $data['fulfillment_type']) : null,
            'delivery_address' => $data['delivery_address'] ?? null,
            'delivery_reference' => $data['delivery_reference'] ?? null,
            'delivery_recipient_name' => $data['delivery_recipient_name'] ?? null,
            'delivery_recipient_phone' => $data['delivery_recipient_phone'] ?? null,
            'payment_method_note' => $data['payment_method_note'] ?? null,
        ])->save();
    }

    public function validatePrescription(PrescriptionReading $reading, User $analyst): void
    {
        $reading->forceFill([
            'validated_by_user_id' => $analyst->getKey(),
            'validated_at' => now(),
        ])->save();

        $this->audit('whatsapp_prescription_validated', 'WhatsApp · Récipe validado por '.$analyst->name, $reading->conversation, $analyst);
    }

    private function assertOpen(WhatsappConversation $conversation): void
    {
        if (! $conversation->isOpen()) {
            throw ValidationException::withMessages([
                'conversation' => 'Esta conversación ya se convirtió en pedido o está cerrada.',
            ]);
        }
    }

    private function systemNote(WhatsappConversation $conversation, string $text): void
    {
        WhatsappMessage::query()->create([
            'whatsapp_conversation_id' => $conversation->getKey(),
            'author' => WhatsappMessageAuthor::System,
            'type' => WhatsappMessageType::Text,
            'body' => $text,
        ]);
    }

    private function audit(string $event, string $description, ?WhatsappConversation $conversation, User $analyst): void
    {
        if (! $conversation instanceof WhatsappConversation) {
            return;
        }

        AuditLogger::record(
            $event,
            $description,
            WhatsappConversation::class,
            $conversation->getKey(),
            $conversation->contact?->displayName(),
            ['module' => 'whatsapp_agent'],
            user: $analyst,
        );
    }
}
