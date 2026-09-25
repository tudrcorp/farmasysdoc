<?php

namespace App\Filament\Resources\MedicationQuotes\Actions;

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductCategory;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Livewire\Component as LivewireComponent;

final class RegisterQuoteProductAction
{
    public static function make(): Action
    {
        return Action::make('registerQuoteProduct')
            ->label('Crear producto')
            ->modalHeading('Producto que no está en inventario')
            ->modalDescription('Se crea en el catálogo con el costo indicado. El precio de la cotización arranca en el precio de venta calculado y puede cambiarlo.')
            ->modalSubmitActionLabel('Crear y agregar')
            ->icon(Heroicon::Plus)
            ->schema([
                TextInput::make('name')
                    ->label('Nombre del medicamento')
                    ->required()
                    ->maxLength(255),
                Select::make('product_category_id')
                    ->label('Categoría')
                    ->options(fn (): array => ProductCategory::query()
                        ->where('is_active', true)
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->required()
                    ->native(false),
                TextInput::make('cost_price')
                    ->label('Costo')
                    ->numeric()
                    ->required()
                    ->minValue(0)
                    ->prefix('USD')
                    ->default(0),
            ])
            ->action(function (array $data, LivewireComponent $livewire): void {
                $name = trim((string) $data['name']);
                $baseSlug = Str::slug($name);
                $slug = ($baseSlug !== '' ? $baseSlug : 'medicamento').'-'.Str::lower(Str::random(8));
                while (Product::query()->where('slug', $slug)->exists()) {
                    $slug = ($baseSlug !== '' ? $baseSlug : 'medicamento').'-'.Str::lower(Str::random(8));
                }

                do {
                    $sku = 'SKU-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
                } while (Product::query()->where('sku', $sku)->exists());

                $actor = auth()->user()?->email ?? auth()->user()?->name ?? 'sistema';

                $product = new Product([
                    'name' => $name,
                    'slug' => $slug,
                    'sku' => $sku,
                    'product_category_id' => (int) $data['product_category_id'],
                    'cost_price' => (float) $data['cost_price'],
                    'discount_percent' => 0,
                    'applies_vat' => true,
                    'is_active' => true,
                    'created_by' => $actor,
                    'updated_by' => $actor,
                ]);
                $product->product_type = ProductType::Medication->value;
                $product->save();

                $product = $product->fresh() ?? $product;
                $lines = $livewire->data['lines'] ?? [];
                if (! is_array($lines)) {
                    $lines = [];
                }

                $lines[] = [
                    'product_id' => $product->getKey(),
                    'description' => $product->name,
                    'quantity' => 1,
                    'unit_price_usd' => number_format((float) $product->sale_price, 2, '.', ''),
                    'line_total_usd' => number_format((float) $product->sale_price, 2, '.', ''),
                ];
                $livewire->data['lines'] = array_values($lines);

                Notification::make()
                    ->title('Producto creado')
                    ->body($product->name.' quedó en la cotización. Existencia actual: 0.')
                    ->success()
                    ->send();
            });
    }
}
