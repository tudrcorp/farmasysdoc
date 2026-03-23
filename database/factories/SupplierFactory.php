<?php

namespace Database\Factories;

use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Supplier>
 */
class SupplierFactory extends Factory
{
    protected $model = Supplier::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $company = fake()->company();

        return [
            'code' => fake()->unique()->bothify('PROV-####'),
            'legal_name' => $company.' S.A.S.',
            'trade_name' => $company,
            'tax_id' => fake()->unique()->numerify('9########'),
            'email' => fake()->unique()->safeEmail(),
            'phone' => fake()->phoneNumber(),
            'mobile_phone' => fake()->optional()->phoneNumber(),
            'website' => fake()->optional()->url(),
            'address' => fake()->streetAddress(),
            'city' => fake()->city(),
            'state' => fake()->randomElement(['Cundinamarca', 'Antioquia', 'Valle del Cauca']),
            'country' => 'Colombia',
            'contact_name' => fake()->name(),
            'contact_email' => fake()->safeEmail(),
            'contact_phone' => fake()->phoneNumber(),
            'payment_terms' => fake()->optional()->randomElement(['Contado', '30 días', '45 días', '60 días']),
            'notes' => fake()->optional()->sentence(),
            'is_active' => true,
            'created_by' => null,
            'updated_by' => null,
        ];
    }
}
