<?php

namespace Database\Factories;

use App\Enums\FiscalDocumentStatus;
use App\Enums\FiscalDocumentType;
use App\Models\FiscalDocument;
use App\Models\FiscalPrinter;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FiscalDocument>
 */
class FiscalDocumentFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'fiscal_printer_id' => FiscalPrinter::factory(),
            'sale_id' => null,
            'type' => FiscalDocumentType::XReport,
            'status' => FiscalDocumentStatus::Pending,
            'payload' => ['type' => FiscalDocumentType::XReport->value],
            'attempts' => 0,
        ];
    }

    public function printed(string $fiscalNumber = '00000001'): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => FiscalDocumentStatus::Printed,
            'fiscal_number' => $fiscalNumber,
            'printed_at' => now(),
        ]);
    }
}
