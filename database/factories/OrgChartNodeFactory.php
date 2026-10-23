<?php

namespace Database\Factories;

use App\Models\OrgChart;
use App\Models\OrgChartNode;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<OrgChartNode>
 */
class OrgChartNodeFactory extends Factory
{
    protected $model = OrgChartNode::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'org_chart_id' => OrgChart::factory(),
            'title' => fake()->jobTitle(),
            'shape' => 'card',
            'area' => null,
            'position_x' => 0,
            'position_y' => 0,
        ];
    }
}
