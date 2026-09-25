<?php

namespace App\Support\Quotes;

use App\Models\MedicationQuote;
use App\Models\MedicationQuoteLine;
use Barryvdh\DomPDF\Facade\Pdf;
use Barryvdh\DomPDF\PDF as DomPdf;
use Illuminate\Support\Collection;

final class MedicationQuotePdfFactory
{
    public function make(MedicationQuote $quote): DomPdf
    {
        $quote->loadMissing('lines');

        return Pdf::loadView('pdf.medication-quote', [
            'quote' => $quote,
            'company_name' => (string) config('fiscal.retention_agent.name'),
            'company_rif' => (string) config('fiscal.retention_agent.rif'),
            'company_address' => (string) config('fiscal.retention_agent.address'),
            'lines' => $this->printedLines($quote),
        ])->setPaper('letter');
    }

    public function output(MedicationQuote $quote): string
    {
        return $this->make($quote)->output();
    }

    /**
     * Precios ya con el ajuste aplicado. El PDF no muestra el porcentaje.
     *
     * @return list<array{description: string, quantity: float, unit_price: float, line_total: float}>
     */
    public function printedLines(MedicationQuote $quote): array
    {
        /** @var Collection<int, MedicationQuoteLine> $lines */
        $lines = $quote->lines;
        $subtotal = (float) $quote->subtotal_usd;
        $total = (float) $quote->total_usd;
        $factor = $subtotal > 0 ? $total / $subtotal : 1.0;
        $printed = [];
        $running = 0.0;
        $lastIndex = $lines->count() - 1;

        foreach ($lines->values() as $index => $line) {
            $isLast = $index === $lastIndex;
            $lineTotal = $isLast
                ? round($total - $running, 2)
                : round((float) $line->line_total_usd * $factor, 2);
            $quantity = (float) $line->quantity;
            $unitPrice = $quantity > 0 ? round($lineTotal / $quantity, 2) : 0.0;
            $running += $lineTotal;

            $printed[] = [
                'description' => (string) $line->description,
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
            ];
        }

        return $printed;
    }
}
