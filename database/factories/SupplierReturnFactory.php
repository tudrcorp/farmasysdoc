<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\Supplier;
use App\Models\SupplierReturn;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<SupplierReturn>
 */
class SupplierReturnFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'supplier_id' => Supplier::factory(),
            'received_on' => now()->toDateString(),
            'notes' => fake()->sentence(),
        ];
    }
}
