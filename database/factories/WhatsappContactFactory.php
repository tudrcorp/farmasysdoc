<?php

namespace Database\Factories;

use App\Models\WhatsappContact;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<WhatsappContact>
 */
class WhatsappContactFactory extends Factory
{
    protected $model = WhatsappContact::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'phone' => '58'.fake()->randomElement(['412', '414', '424', '416']).fake()->numerify('#######'),
            'profile_name' => fake()->firstName(),
            'last_message_at' => now(),
        ];
    }

    public function registered(string $fullName = 'Carmen Pérez', string $documentNumber = '12345678'): static
    {
        return $this->state(fn (): array => [
            'full_name' => $fullName,
            'document_type' => 'V',
            'document_number' => $documentNumber,
        ]);
    }
}
