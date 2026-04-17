<?php

namespace App\Filament\Resources\Purchases\Pages\Concerns;

use App\Filament\Resources\Purchases\Schemas\PurchaseForm;
use App\Models\Product;
use App\Support\Finance\DefaultVatRate;
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

    protected function appendPurchaseLineForProduct(Product $product, ?float $unitCostOverride = null): void
    {
        $this->data['items'] ??= [];
        $defaultVat = $product->applies_vat
            ? DefaultVatRate::percent()
            : 0.0;

        $lineDiscount = max(0.0, min(100.0, (float) ($product->discount_percent ?? 0)));

        $code = filled($product->barcode)
            ? (string) $product->barcode
            : (string) $product->sku;

        $unitCost = $unitCostOverride !== null
            ? max(0.0, $unitCostOverride)
            : (float) ($product->cost_price ?? 0);
        $lineState = [
            'quantity_ordered' => 1,
            'unit_cost' => $unitCost,
            'line_discount_percent' => $lineDiscount,
            'line_vat_percent' => $defaultVat,
        ];
        $amounts = PurchaseDocumentTotals::lineAmounts($lineState);

        $this->data['items'][] = [
            'product_id' => $product->id,
            'product_name_snapshot' => $product->name,
            'sku_snapshot' => $code,
            'unit_cost' => $unitCost,
            'line_discount_percent' => $lineDiscount,
            'line_vat_percent' => $defaultVat,
            'quantity_ordered' => 1,
            'quantity_received' => 0,
            'line_subtotal' => $amounts['line_subtotal'],
            'tax_amount' => $amounts['tax_amount'],
            'line_total' => $amounts['line_total'],
            'lot_expiration_month_year' => null,
        ];

        $this->recalculatePurchaseDocumentTotalsFromItems();
    }

    protected function recalculatePurchaseDocumentTotalsFromItems(): void
    {
        $items = $this->data['items'] ?? [];
        $docDisc = (float) ($this->data['document_discount_percent'] ?? 0);
        $header = PurchaseDocumentTotals::documentHeaderWithDocumentDiscount(is_array($items) ? $items : [], $docDisc);

        foreach ($header as $key => $value) {
            $this->data[$key] = $value;
        }
    }
}
