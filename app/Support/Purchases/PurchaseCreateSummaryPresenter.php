<?php

namespace App\Support\Purchases;

use App\Enums\PurchaseEntryCurrency;
use App\Filament\Resources\Purchases\Schemas\PurchaseForm;
use App\Support\Finance\DefaultVatRate;

/**
 * Normaliza el estado del formulario de compra para la vista de resumen previa al guardado.
 */
final class PurchaseCreateSummaryPresenter
{
    /**
     * @param  array<string, mixed>  $state
     * @return array<string, mixed>
     */
    public static function fromFormState(array $state): array
    {
        $items = $state['items'] ?? [];
        $rows = [];
        $lineNo = 1;

        if (is_array($items)) {
            foreach ($items as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $label = trim((string) ($row['product_name_snapshot'] ?? '').' · '.(string) ($row['sku_snapshot'] ?? ''));
                if ($label === '·') {
                    $label = '—';
                }
                $rows[] = [
                    'index' => $lineNo++,
                    'product_label' => $label,
                    'quantity' => (float) ($row['quantity_ordered'] ?? 0),
                    'unit_cost' => (float) ($row['unit_cost'] ?? 0),
                    'line_discount_percent' => (float) ($row['line_discount_percent'] ?? 0),
                    'line_vat_percent' => (float) ($row['line_vat_percent'] ?? 0),
                    'tax_amount' => (float) ($row['tax_amount'] ?? 0),
                    'line_subtotal' => (float) ($row['line_subtotal'] ?? 0),
                    'line_total' => (float) ($row['line_total'] ?? 0),
                    'lot_expiration' => filled($row['lot_expiration_month_year'] ?? null)
                        ? (string) $row['lot_expiration_month_year']
                        : '—',
                ];
            }
        }

        $supplierLabel = PurchaseForm::supplierDisplayNameForSupplierId($state['supplier_id'] ?? null);

        $currency = PurchaseEntryCurrency::tryFrom((string) ($state['entry_currency'] ?? PurchaseEntryCurrency::USD->value))
            ?? PurchaseEntryCurrency::USD;
        $currencyPrefix = $currency->moneyPrefix();

        return [
            'supplier_label' => $supplierLabel !== '' ? $supplierLabel : '—',
            'supplier_invoice_number' => filled($state['supplier_invoice_number'] ?? null) ? (string) $state['supplier_invoice_number'] : '—',
            'supplier_control_number' => filled($state['supplier_control_number'] ?? null) ? (string) $state['supplier_control_number'] : '—',
            'supplier_invoice_date' => filled($state['supplier_invoice_date'] ?? null) ? (string) $state['supplier_invoice_date'] : '—',
            'payment_due_date' => filled($state['payment_due_date'] ?? null) ? (string) $state['payment_due_date'] : '—',
            'registered_in_system_date' => filled($state['registered_in_system_date'] ?? null) ? (string) $state['registered_in_system_date'] : '—',
            'payment_status_label' => PurchasePaymentStatus::label(isset($state['payment_status']) ? (string) $state['payment_status'] : null),
            'entry_currency' => $currency->value,
            'currency_prefix' => $currencyPrefix,
            'document_discount_percent' => (float) ($state['document_discount_percent'] ?? 0),
            'declared_invoice_total' => (float) ($state['declared_invoice_total'] ?? 0),
            'subtotal' => (float) ($state['subtotal'] ?? 0),
            'document_discount_amount' => (float) ($state['document_discount_amount'] ?? 0),
            'net_exempt_after_document_discount' => (float) ($state['net_exempt_after_document_discount'] ?? 0),
            'net_taxable_after_document_discount' => (float) ($state['net_taxable_after_document_discount'] ?? 0),
            'tax_total' => (float) ($state['tax_total'] ?? 0),
            'total' => (float) ($state['total'] ?? 0),
            'discount_total_lines' => (float) ($state['discount_total'] ?? 0),
            'global_vat_percent' => DefaultVatRate::percent(),
            'rows' => $rows,
        ];
    }

    /**
     * Datos para el pie del modal de confirmación (total declarado vs calculado).
     *
     * @param  array<string, mixed>  $state
     * @return array{match: bool, declared: float, calculated: float, currency_prefix: string, diff: float}
     */
    public static function footerTotalsPayload(array $state): array
    {
        $summary = self::fromFormState($state);
        $declared = (float) ($summary['declared_invoice_total'] ?? 0);
        $calculated = (float) ($summary['total'] ?? 0);
        $match = PurchaseDeclaredInvoiceTotalTolerance::matches($declared, $calculated);

        return [
            'match' => $match,
            'declared' => $declared,
            'calculated' => $calculated,
            'currency_prefix' => (string) ($summary['currency_prefix'] ?? '$'),
            'diff' => abs($declared - $calculated),
        ];
    }
}
