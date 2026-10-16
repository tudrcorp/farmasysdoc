<?php

namespace Database\Factories;

use App\Enums\FiscalZReportSource;
use App\Models\FiscalPrinter;
use App\Models\FiscalZReport;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<FiscalZReport>
 */
class FiscalZReportFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $base = fake()->randomFloat(2, 10000, 90000);

        return [
            'fiscal_printer_id' => FiscalPrinter::factory(),
            'branch_id' => fn (array $attributes): int => (int) FiscalPrinter::query()->findOrFail($attributes['fiscal_printer_id'])->branch_id,
            'printer_serial' => fn (array $attributes): string => (string) preg_replace('/[^A-Z0-9]/', '', strtoupper((string) FiscalPrinter::query()->findOrFail($attributes['fiscal_printer_id'])->fiscal_registry)),
            'z_number' => fake()->unique()->numberBetween(1, 9000),
            'report_date' => now()->toDateString(),
            'report_datetime' => now(),
            'last_invoice_number' => fake()->numberBetween(1000, 90000),
            'exempt_sales_ves' => fake()->randomFloat(2, 1000, 50000),
            'general_base_sales_ves' => $base,
            'general_tax_sales_ves' => round($base * 0.16, 2),
            'general_rate_percent' => 16,
            'source' => FiscalZReportSource::MemoryRead,
            'content_hash' => '',
            'captured_at' => now(),
        ];
    }

    public function configure(): static
    {
        return $this->afterMaking(function (FiscalZReport $report): void {
            $attributes = $report->getAttributes();
            $attributes['report_date'] = $report->report_date?->toDateString();
            $report->content_hash = FiscalZReport::computeContentHash($attributes);
        });
    }
}
