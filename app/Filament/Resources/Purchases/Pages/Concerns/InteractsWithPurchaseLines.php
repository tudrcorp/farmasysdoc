<?php

namespace App\Filament\Resources\Purchases\Pages\Concerns;

use App\Filament\Resources\Purchases\Schemas\PurchaseForm;
use App\Models\Product;
use App\Support\Purchases\PurchaseDocumentTotals;

trait InteractsWithPurchaseLines
{
    public function addPurchaseLineFromSearch(?string $search = null): void
    {
        $term = trim($search ?? (string) ($this->data['purchase_line_product_search'] ?? ''));
        if ($term === '') {
            return;
        }

        $product = PurchaseForm::findProductForPurchaseLineSearch($term);

        if (! $product instanceof Product) {
            $this->mountAction('quickCreatePurchaseProduct', [
                'search' => $term,
                'supplier_id' => $this->data['supplier_id'] ?? null,
            ]);

            return;
        }

        $this->appendPurchaseLineForProduct($product);
        $this->data['purchase_line_product_search'] = '';
    }

    protected function appendPurchaseLineForProduct(Product $product): void
    {
        $this->data['items'] ??= [];
        $defaultVat = $product->applies_vat
            ? (float) config('orders.default_vat_rate_percent', 19)
            : 0.0;

        $code = filled($product->barcode)
            ? (string) $product->barcode
            : (string) $product->sku;

        $unitCost = (float) ($product->cost_price ?? 0);
        $lineState = [
            'quantity_ordered' => 1,
            'unit_cost' => $unitCost,
            'line_discount_percent' => 0.0,
            'line_vat_percent' => $defaultVat,
        ];
        $amounts = PurchaseDocumentTotals::lineAmounts($lineState);

        $this->data['items'][] = [
            'product_id' => $product->id,
            'product_name_snapshot' => $product->name,
            'sku_snapshot' => $code,
            'unit_cost' => $unitCost,
            'line_discount_percent' => 0.0,
            'line_vat_percent' => $defaultVat,
            'quantity_ordered' => 1,
            'quantity_received' => 0,
            'line_subtotal' => $amounts['line_subtotal'],
            'tax_amount' => $amounts['tax_amount'],
            'line_total' => $amounts['line_total'],
        ];

        $this->recalculatePurchaseDocumentTotalsFromItems();
    }

    protected function recalculatePurchaseDocumentTotalsFromItems(): void
    {
        $totals = PurchaseDocumentTotals::documentTotals($this->data['items'] ?? []);
        $this->data['subtotal'] = $totals['subtotal'];
        $this->data['tax_total'] = $totals['tax_total'];
        $this->data['discount_total'] = $totals['discount_total'];
        $this->data['total'] = $totals['total'];
    }
}
