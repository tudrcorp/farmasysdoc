<?php

namespace App\Services\WhatsappAgent;

use App\Enums\WhatsappConversationStatus;
use App\Models\WhatsappConversation;
use App\Services\Audit\AuditLogger;
use App\Support\WhatsappAgent\WhatsappAnalystSchedule;

/**
 * Pasa la conversación al equipo. En horario avisa a los analistas en la campana;
 * fuera de horario la deja en «Pendiente para la jornada» con el resumen.
 *
 * El asistente sigue respondiendo al cliente hasta que un analista toma la conversación.
 */
final class WhatsappConversationEscalator
{
    public function __construct(private readonly WhatsappAnalystNotifier $notifier) {}

    public function escalate(WhatsappConversation $conversation, string $reason, ?string $summary = null): WhatsappConversation
    {
        if (! $conversation->isOpen()) {
            return $conversation;
        }

        $alreadyWaiting = in_array($conversation->status, WhatsappConversationStatus::awaitingAnalyst(), true)
            || $conversation->status === WhatsappConversationStatus::InProgress;

        $withinHours = WhatsappAnalystSchedule::current()->isWithinHours();

        $attributes = [
            'escalation_reason' => mb_substr(trim($reason), 0, 500),
            'summary' => filled($summary) ? trim((string) $summary) : $conversation->summary,
            'state' => [...($conversation->state ?? []), 'stage' => 'enviado_analista'],
        ];

        if (! $alreadyWaiting) {
            $attributes['status'] = $withinHours ? WhatsappConversationStatus::NeedsAnalyst : WhatsappConversationStatus::PendingNextShift;
            $attributes['escalated_at'] = now();
            $attributes['escalated_off_hours'] = ! $withinHours;
        }

        $conversation->forceFill($attributes)->save();

        AuditLogger::record(
            'whatsapp_conversation_escalated',
            'WhatsApp · Conversación enviada al equipo: '.mb_substr($reason, 0, 120),
            WhatsappConversation::class,
            $conversation->getKey(),
            $conversation->contact?->displayName(),
            [
                'module' => 'whatsapp_agent',
                'off_hours' => ! $withinHours,
                'reason' => $reason,
            ],
        );

        if (! $alreadyWaiting && $withinHours) {
            $this->notifier->conversationEscalated($conversation);
        }

        return $conversation->refresh();
    }
}
