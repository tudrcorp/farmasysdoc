<?php

namespace App\Services\Fiscal;

use App\Enums\FiscalDocumentType;
use App\Models\Client;
use App\Models\FiscalDocument;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Support\Finance\DefaultVatRate;
use App\Support\Sales\PosPaymentMethodOptions;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Congela en Bs (a la tasa BCV de la venta) lo que el agente local debe enviar a la máquina fiscal.
 *
 * Contrato v1 con el agente: los precios van **sin IVA** con su alícuota; la máquina fiscal calcula
 * impuestos, IGTF (sobre pagos en divisas) y asigna el número fiscal.
 */
final class FiscalDocumentPayloadBuilder
{
    public const VERSION = 1;

    public const TAX_CODE_GENERAL = 'G';

    public const TAX_CODE_EXEMPT = 'E';

    /**
     * @return array<string, mixed>
     */
    public function forInvoice(Sale $sale, ?string $mixedVesPaymentMethod = null): array
    {
        $sale->loadMissing(['client', 'items']);

        $rate = $this->vesPerUsd($sale);
        $merchandiseUsd = round((float) $sale->subtotal - (float) $sale->discount_total + (float) $sale->tax_total, 2);

        return [
            'version' => self::VERSION,
            'type' => FiscalDocumentType::Invoice->value,
            'sale_number' => (string) $sale->sale_number,
            'sold_at' => ($sale->sold_at ?? $sale->created_at)?->toIso8601String(),
            'exchange_rate_ves_per_usd' => $rate,
            'customer' => $this->customer($sale->client),
            'items' => $this->items($sale, $rate),
            'payments' => $this->payments($sale, $rate, $merchandiseUsd, $mixedVesPaymentMethod),
            'expected' => $this->expectedTotals($sale, $rate),
        ];
    }

    /**
     * Nota de crédito por devolución total de una venta ya facturada en máquina fiscal.
     *
     * @return array<string, mixed>
     */
    public function forCreditNote(Sale $sale, FiscalDocument $invoice): array
    {
        if (blank($invoice->fiscal_number)) {
            throw new RuntimeException('La factura fiscal original no tiene número fiscal.');
        }

        $sale->loadMissing(['client', 'items']);

        $invoicePayload = is_array($invoice->payload) ? $invoice->payload : [];
        $rate = (float) ($invoicePayload['exchange_rate_ves_per_usd'] ?? $this->vesPerUsd($sale));

        return [
            'version' => self::VERSION,
            'type' => FiscalDocumentType::CreditNote->value,
            'sale_number' => (string) $sale->sale_number,
            'exchange_rate_ves_per_usd' => $rate,
            'customer' => $invoicePayload['customer'] ?? $this->customer($sale->client),
            'original_invoice' => [
                'fiscal_number' => (string) $invoice->fiscal_number,
                'printer_serial' => (string) ($invoice->printer_serial ?? $invoice->fiscalPrinter?->fiscal_registry ?? ''),
                'date' => ($invoice->printer_datetime ?? $invoice->printed_at)?->format('Y-m-d'),
                'time' => ($invoice->printer_datetime ?? $invoice->printed_at)?->format('H:i'),
            ],
            'items' => $invoicePayload['items'] ?? $this->items($sale, $rate),
            'payments' => $invoicePayload['payments'] ?? [],
            'expected' => $invoicePayload['expected'] ?? $this->expectedTotals($sale, $rate),
        ];
    }

    /**
     * Factura del laboratorio fiscal a partir de una venta ficticia en Bs (sin venta, sin inventario).
     *
     * @param  array{items: list<array{description: string, quantity: float|int|string, unit_price_ves: float|int|string, tax: string}>, payment_method: string, customer_document?: ?string, customer_name?: ?string}  $data
     * @return array<string, mixed>
     */
    public function forTestInvoice(array $data, bool $autoCreditNote): array
    {
        $vatRate = DefaultVatRate::percent();
        $items = [];
        $subtotal = 0.0;
        $tax = 0.0;

        foreach ($data['items'] as $item) {
            $quantity = round((float) $item['quantity'], 3);
            $unitPrice = round((float) $item['unit_price_ves'], 2);
            $isTaxed = ($item['tax'] ?? 'E') === self::TAX_CODE_GENERAL;
            $lineBase = round($quantity * $unitPrice, 2);

            $subtotal += $lineBase;
            $tax += $isTaxed ? round($lineBase * $vatRate / 100, 2) : 0.0;

            $items[] = [
                'code' => null,
                'description' => Str::upper(Str::limit(trim((string) $item['description']), 40, '')),
                'quantity' => $quantity,
                'unit_price_ves' => $unitPrice,
                'discount_ves' => 0.0,
                'tax_code' => $isTaxed ? self::TAX_CODE_GENERAL : self::TAX_CODE_EXEMPT,
                'tax_rate_percent' => $isTaxed ? $vatRate : 0.0,
            ];
        }

        $subtotal = round($subtotal, 2);
        $tax = round($tax, 2);
        $total = round($subtotal + $tax, 2);
        $method = (string) $data['payment_method'];

        return [
            'version' => self::VERSION,
            'type' => FiscalDocumentType::Invoice->value,
            'test' => true,
            'auto_credit_note' => $autoCreditNote,
            'sale_number' => 'PRUEBA-'.now()->format('ymdHis'),
            'sold_at' => now()->toIso8601String(),
            'exchange_rate_ves_per_usd' => 1.0,
            'customer' => [
                'document' => filled($data['customer_document'] ?? null)
                    ? Str::upper((string) preg_replace('/[^0-9A-Za-z]/', '', (string) $data['customer_document']))
                    : null,
                'name' => filled($data['customer_name'] ?? null) ? Str::upper(trim((string) $data['customer_name'])) : 'CONSUMIDOR FINAL',
                'address' => null,
                'phone' => null,
            ],
            'items' => $items,
            'payments' => [[
                'method' => $method,
                'amount_ves' => $total,
                'is_foreign_currency' => in_array($method, ['cash_usd', 'transfer_usd', 'zelle'], true),
            ]],
            'expected' => [
                'subtotal_ves' => $subtotal,
                'discount_ves' => 0.0,
                'tax_ves' => $tax,
                'igtf_ves' => 0.0,
                'total_ves' => $total,
            ],
        ];
    }

    /**
     * Nota de crédito que anula por completo una factura ya impresa, usando solo su payload
     * (laboratorio fiscal: no hay venta asociada).
     *
     * @return array<string, mixed>
     */
    public function forCreditNoteFromInvoice(FiscalDocument $invoice): array
    {
        if (blank($invoice->fiscal_number)) {
            throw new RuntimeException('La factura de prueba no tiene número fiscal.');
        }

        $invoicePayload = is_array($invoice->payload) ? $invoice->payload : [];
        $printedAt = $invoice->printer_datetime ?? $invoice->printed_at;

        return array_merge($invoicePayload, [
            'type' => FiscalDocumentType::CreditNote->value,
            'auto_credit_note' => false,
            'original_invoice' => [
                'fiscal_number' => (string) $invoice->fiscal_number,
                'printer_serial' => (string) ($invoice->printer_serial ?? $invoice->fiscalPrinter?->fiscal_registry ?? ''),
                'date' => $printedAt?->format('Y-m-d'),
                'time' => $printedAt?->format('H:i'),
            ],
        ]);
    }

    /**
     * @param  list<string>  $lines
     * @return array<string, mixed>
     */
    public function forNonFiscalTicket(array $lines): array
    {
        return [
            'version' => self::VERSION,
            'type' => FiscalDocumentType::NonFiscalTicket->value,
            'test' => true,
            'lines' => array_values(array_filter(array_map(
                fn (string $line): string => Str::upper(Str::limit(trim($line), 40, '')),
                $lines,
            ), fn (string $line): bool => $line !== '')),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function forReport(FiscalDocumentType $type): array
    {
        return [
            'version' => self::VERSION,
            'type' => $type->value,
        ];
    }

    public function vesPerUsd(Sale $sale): float
    {
        $stored = (float) ($sale->bcv_ves_per_usd ?? 0);

        if ($stored <= 0) {
            throw new RuntimeException('La venta '.$sale->sale_number.' no tiene tasa BCV registrada; no se puede facturar en Bs.');
        }

        return $stored;
    }

    /**
     * @return array{document: ?string, name: string, address: ?string, phone: ?string}
     */
    private function customer(?Client $client): array
    {
        if (! $client instanceof Client) {
            return [
                'document' => null,
                'name' => 'CONSUMIDOR FINAL',
                'address' => null,
                'phone' => null,
            ];
        }

        $number = Str::upper(preg_replace('/[^0-9A-Za-z]/', '', (string) ($client->document_number ?? '')) ?? '');
        $prefix = match (Str::upper(trim((string) ($client->document_type ?? '')))) {
            'CC' => 'V',
            'CE' => 'E',
            default => '',
        };

        if ($prefix !== '' && preg_match('/^[VEJGPC]/', $number) !== 1) {
            $number = $prefix.$number;
        }

        return [
            'document' => $number !== '' ? $number : null,
            'name' => Str::upper(Str::limit(trim((string) $client->name), 60, '')) ?: 'CONSUMIDOR FINAL',
            'address' => filled($client->address) ? Str::upper(Str::limit(trim((string) $client->address), 120, '')) : null,
            'phone' => filled($client->phone) ? trim((string) $client->phone) : null,
        ];
    }

    /**
     * @return list<array{code: ?string, description: string, quantity: float, unit_price_ves: float, discount_ves: float, tax_code: string, tax_rate_percent: float}>
     */
    private function items(Sale $sale, float $rate): array
    {
        $vatRate = DefaultVatRate::percent();
        $items = [];

        foreach ($sale->items as $item) {
            if (! $item instanceof SaleItem) {
                continue;
            }

            $isTaxed = (float) $item->tax_amount > 0.00001;

            $items[] = [
                'code' => filled($item->sku_snapshot) ? (string) $item->sku_snapshot : null,
                'description' => Str::upper(trim((string) ($item->product_name_snapshot ?? 'PRODUCTO'))),
                'quantity' => round((float) $item->quantity, 3),
                'unit_price_ves' => round((float) $item->unit_price * $rate, 2),
                'discount_ves' => round(max(0.0, (float) $item->discount_amount) * $rate, 2),
                'tax_code' => $isTaxed ? self::TAX_CODE_GENERAL : self::TAX_CODE_EXEMPT,
                'tax_rate_percent' => $isTaxed ? $vatRate : 0.0,
            ];
        }

        return $items;
    }

    /**
     * Medios de pago sin IGTF: la máquina fiscal lo calcula sobre los pagos marcados como divisa.
     *
     * @return list<array{method: string, amount_ves: float, is_foreign_currency: bool}>
     */
    private function payments(Sale $sale, float $rate, float $merchandiseUsd, ?string $mixedVesPaymentMethod): array
    {
        $method = (string) $sale->payment_method;

        if ($method !== 'mixed') {
            $code = $this->paymentCode($method);

            return [[
                'method' => $code,
                'amount_ves' => round($merchandiseUsd * $rate, 2),
                'is_foreign_currency' => $this->isForeignCurrency($code),
            ]];
        }

        $foreignUsd = min(max(0.0, (float) $sale->payment_usd), $merchandiseUsd);
        $foreignVes = round($foreignUsd * $rate, 2);
        $localVes = round($merchandiseUsd * $rate - $foreignVes, 2);

        $payments = [];

        if ($foreignVes > 0.0) {
            $payments[] = [
                'method' => 'cash_usd',
                'amount_ves' => $foreignVes,
                'is_foreign_currency' => true,
            ];
        }

        if ($localVes > 0.0) {
            $code = $this->paymentCode($mixedVesPaymentMethod ?? 'efectivo_ves');
            $payments[] = [
                'method' => $code,
                'amount_ves' => $localVes,
                'is_foreign_currency' => $this->isForeignCurrency($code),
            ];
        }

        return $payments;
    }

    /**
     * Código lógico del medio de pago; el agente lo traduce al número de medio programado en la máquina.
     */
    private function paymentCode(string $posMethod): string
    {
        return match ($posMethod) {
            'efectivo_usd' => 'cash_usd',
            'transfer_usd' => 'transfer_usd',
            'zelle' => 'zelle',
            'efectivo_ves' => 'cash_ves',
            'punto_venta_ves' => 'card_ves',
            'transfer_ves' => 'transfer_ves',
            'pago_movil' => 'mobile_payment_ves',
            PosPaymentMethodOptions::CACHEA => 'cashea',
            default => 'other_ves',
        };
    }

    private function isForeignCurrency(string $paymentCode): bool
    {
        return in_array($paymentCode, ['cash_usd', 'transfer_usd', 'zelle'], true);
    }

    /**
     * @return array{subtotal_ves: float, discount_ves: float, tax_ves: float, igtf_ves: float, total_ves: float}
     */
    private function expectedTotals(Sale $sale, float $rate): array
    {
        return [
            'subtotal_ves' => round((float) $sale->subtotal * $rate, 2),
            'discount_ves' => round((float) $sale->discount_total * $rate, 2),
            'tax_ves' => round((float) $sale->tax_total * $rate, 2),
            'igtf_ves' => round((float) ($sale->igtf_total ?? 0) * $rate, 2),
            'total_ves' => round((float) $sale->total * $rate, 2),
        ];
    }
}
