<?php

namespace Database\Factories;

use App\Enums\WhatsappMessageAuthor;
use App\Enums\WhatsappMessageType;
use App\Models\WhatsappConversation;
use App\Models\WhatsappMessage;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<WhatsappMessage>
 */
class WhatsappMessageFactory extends Factory
{
    protected $model = WhatsappMessage::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'whatsapp_conversation_id' => WhatsappConversation::factory(),
            'author' => WhatsappMessageAuthor::Customer,
            'type' => WhatsappMessageType::Text,
            'body' => fake()->sentence(),
            'provider_message_id' => 'false_'.Str::random(20).'@c.us',
        ];
    }

    public function fromAgent(): static
    {
        return $this->state(fn (): array => [
            'author' => WhatsappMessageAuthor::Agent,
            'provider_message_id' => null,
        ]);
    }
}
