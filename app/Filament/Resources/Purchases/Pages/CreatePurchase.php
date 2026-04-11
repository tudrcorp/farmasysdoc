<?php

namespace App\Filament\Resources\Purchases\Pages;

use App\Filament\Resources\Purchases\Actions\QuickCreatePurchaseProductAction;
use App\Filament\Resources\Purchases\Actions\QuickCreateSupplierAction;
use App\Filament\Resources\Purchases\Pages\Concerns\InteractsWithPurchaseLines;
use App\Filament\Resources\Purchases\PurchaseResource;
use App\Models\Product;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;

class CreatePurchase extends CreateRecord
{
    use InteractsWithPurchaseLines;

    protected static string $resource = PurchaseResource::class;

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            QuickCreateSupplierAction::make(function (int $supplierId): void {
                $this->data['supplier_id'] = $supplierId;
            }),
            QuickCreatePurchaseProductAction::make(function (Product $product): void {
                $this->appendPurchaseLineForProduct($product);
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

        return collect($data)->except(['items'])->all();
    }
}
