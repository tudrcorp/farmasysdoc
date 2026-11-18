<?php

namespace Database\Factories;

use App\Enums\WhatsappConversationHandler;
use App\Enums\WhatsappConversationStatus;
use App\Models\User;
use App\Models\WhatsappContact;
use App\Models\WhatsappConversation;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsappConversation>
 */
class WhatsappConversationFactory extends Factory
{
    protected $model = WhatsappConversation::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'whatsapp_contact_id' => WhatsappContact::factory(),
            'status' => WhatsappConversationStatus::Bot,
            'handled_by' => WhatsappConversationHandler::Agent,
            'state' => ['stage' => 'saludo'],
            'last_inbound_at' => now(),
        ];
    }

    public function needsAnalyst(string $reason = 'El cliente pidió hablar con una persona.'): static
    {
        return $this->state(fn (): array => [
            'status' => WhatsappConversationStatus::NeedsAnalyst,
            'escalation_reason' => $reason,
            'escalated_at' => now(),
        ]);
    }

    public function handledBy(User $analyst): static
    {
        return $this->state(fn (): array => [
            'status' => WhatsappConversationStatus::InProgress,
            'handled_by' => WhatsappConversationHandler::Analyst,
            'assigned_user_id' => $analyst->getKey(),
        ]);
    }
}
