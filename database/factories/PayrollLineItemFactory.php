<?php

namespace Database\Factories;

use App\Enums\PayrollLineItemType;
use App\Models\PayrollLine;
use App\Models\PayrollLineItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PayrollLineItem>
 */
class PayrollLineItemFactory extends Factory
{
    protected $model = PayrollLineItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'payroll_line_id' => PayrollLine::factory(),
            'type' => PayrollLineItemType::Base,
            'reference_type' => null,
            'reference_id' => null,
            'concept' => 'Sueldo base quincenal',
            'amount_usd' => 150,
            'amount_ves' => 5475,
        ];
    }
}
