<?php

namespace App\Filament\Resources\Purchases\Pages;

use App\Filament\Resources\Purchases\Actions\QuickCreatePurchaseProductAction;
use App\Filament\Resources\Purchases\Actions\QuickCreateSupplierAction;
use App\Filament\Resources\Purchases\Pages\Concerns\InteractsWithPurchaseLines;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Filament\Resources\Purchases\Schemas\PurchaseForm;
use App\Models\Product;
use App\Support\Purchases\PurchaseDocumentTotals;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchase extends CreateRecord
{
    use InteractsWithPurchaseLines;

    protected static string $resource = PurchaseResource::class;

    protected function afterCreate(): void
    {
        $this->record->refresh();
        $this->record->load('items');
        $this->record->syncProductLotsFromItems();
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            QuickCreateSupplierAction::make(function (int $supplierId): void {
                $this->data['supplier_id'] = (string) $supplierId;
                $this->data['supplier_display_name'] = PurchaseForm::supplierDisplayNameForSupplierId($supplierId);
            }),
            QuickCreatePurchaseProductAction::make(function (Product $product, ?float $unitCostFromModal = null): void {
                $this->appendPurchaseLineForProduct($product, $unitCostFromModal);
                $this->data['purchase_line_product_search'] = '';
            }),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $actor = auth()->user()?->email
            ?? auth()->user()?->name
            ?? 'sistema';

        $data['created_by'] = $actor;
        $data['updated_by'] = $actor;

        if (blank($data['supplier_invoice_date'] ?? null)) {
            $data['supplier_invoice_date'] = now()->toDateString();
        }
        if (blank($data['registered_in_system_date'] ?? null)) {
            $data['registered_in_system_date'] = now()->toDateString();
        }

        $items = $data['items'] ?? [];
        $docDisc = (float) ($data['document_discount_percent'] ?? 0);
        $header = PurchaseDocumentTotals::documentHeaderWithDocumentDiscount(is_array($items) ? $items : [], $docDisc);
        $data = array_merge($data, $header);

        return collect($data)->except(['items', 'supplier_display_name'])->all();
    }
}
