<?php

namespace Database\Factories;

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Product>
 */
class ProductFactory extends Factory
{
    protected $model = Product::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(3, true);

        return [
            'supplier_id' => Supplier::factory(),
            'sku' => fake()->unique()->bothify('SKU-####-???'),
            'barcode' => fake()->unique()->ean13(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('###'),
            'description' => fake()->optional()->paragraph(),
            'image' => null,
            'product_type' => fake()->randomElement(ProductType::cases()),
            'brand' => fake()->optional()->company(),
            'presentation' => fake()->optional()->randomElement(['Caja x 10', 'Frasco 120 ml', 'Bolsa 1 kg']),
            'unit_of_measure' => fake()->randomElement(['unit', 'kg', 'l', 'box', 'pack']),
            'unit_content' => fake()->optional()->randomFloat(3, 0.1, 5),
            'net_content_label' => fake()->optional()->randomElement(['400 ml', '500 g', '1 L']),
            'active_ingredient' => null,
            'concentration' => null,
            'presentation_type' => null,
            'requires_prescription' => false,
            'is_controlled_substance' => false,
            'health_registration_number' => null,
            'ingredients' => null,
            'allergens' => null,
            'nutritional_information' => null,
            'manufacturer' => null,
            'model' => null,
            'warranty_months' => null,
            'medical_device_class' => null,
            'requires_calibration' => false,
            'storage_conditions' => null,
            'is_active' => true,
            'created_by' => 'factory',
            'updated_by' => 'factory',
        ];
    }

    public function medication(): static
    {
        return $this->state(fn (array $attributes): array => [
            'product_type' => ProductType::Medication,
            'active_ingredient' => fake()->sentence(6),
            'concentration' => fake()->randomElement(['500 mg', '10 mg/ml']),
            'presentation_type' => fake()->randomElement(['Tabletas', 'Jarabe', 'Cápsulas', 'Solución inyectable', 'Crema']),
            'requires_prescription' => fake()->boolean(30),
            'health_registration_number' => fake()->bothify('INVIMA-####-##'),
        ]);
    }

    public function food(): static
    {
        return $this->state(fn (array $attributes): array => [
            'product_type' => ProductType::Food,
            'ingredients' => fake()->sentence(),
            'allergens' => fake()->optional()->randomElement(['Contiene gluten', 'Leche', 'Soya']),
            'nutritional_information' => fake()->optional()->paragraph(),
        ]);
    }

    public function medicalEquipment(): static
    {
        return $this->state(fn (array $attributes): array => [
            'product_type' => ProductType::MedicalEquipment,
            'manufacturer' => fake()->company(),
            'model' => fake()->bothify('MD-###'),
            'warranty_months' => fake()->numberBetween(6, 36),
            'medical_device_class' => fake()->randomElement(['I', 'IIa', 'IIb']),
            'requires_calibration' => fake()->boolean(40),
        ]);
    }
}
