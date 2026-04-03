<?php

namespace Database\Factories;

use App\Models\ProductCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ProductCategory>
 */
class ProductCategoryFactory extends Factory
{
    protected $model = ProductCategory::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->words(2, true);

        return [
            'name' => $name,
            'description' => null,
            'image' => null,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('####'),
            'is_active' => true,
            'is_medication' => false,
            'profit_percentage' => 0,
            'created_by' => 'factory',
            'updated_by' => 'factory',
        ];
    }

    public function medication(): static
    {
        return $this->state(fn (array $attributes): array => [
            'is_medication' => true,
            'name' => 'Categoría medicamentos '.fake()->numerify('###'),
            'slug' => 'med-cat-'.fake()->unique()->numerify('####'),
        ]);
    }
}
