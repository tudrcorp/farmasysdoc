<?php

namespace Database\Factories;

use App\Models\OrgChart;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrgChart>
 */
class OrgChartFactory extends Factory
{
    protected $model = OrgChart::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Organigrama '.fake()->unique()->word(),
        ];
    }
}
