<?php

namespace Database\Factories;

use App\Models\Branch;
use App\Models\BranchDailyOperation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<BranchDailyOperation>
 */
class BranchDailyOperationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'branch_id' => Branch::factory(),
            'opened_by_user_id' => User::factory(),
            'opened_at' => now(),
            'closed_by_user_id' => null,
            'closed_at' => null,
        ];
    }

    public function closed(): static
    {
        return $this->state(fn (array $attributes): array => [
            'closed_by_user_id' => User::factory(),
            'closed_at' => now(),
        ]);
    }
}
