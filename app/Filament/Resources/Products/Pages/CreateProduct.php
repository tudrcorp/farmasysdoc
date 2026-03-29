<?php

namespace App\Filament\Resources\Products\Pages;

use App\Filament\Resources\Products\Concerns\HasFarmaadminIosProductPage;
use App\Filament\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateProduct extends CreateRecord
{
    use HasFarmaadminIosProductPage;

    protected static string $resource = ProductResource::class;

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

        if (blank($data['sku'] ?? null)) {
            $data['sku'] = $this->generateUniqueProductSku();
        }

        return $data;
    }

    /**
     * La columna `products.sku` es NOT NULL y única; el formulario no siempre la envía.
     */
    private function generateUniqueProductSku(): string
    {
        do {
            $sku = 'SKU-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (Product::query()->where('sku', $sku)->exists());

        return $sku;
    }

    protected function afterCreate(): void
    {
        $record = $this->record;

        if (blank($record->barcode)) {
            $record->update([
                'barcode' => '00'.$record->id,
            ]);
        }
    }
}
