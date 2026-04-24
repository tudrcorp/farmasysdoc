<?php

namespace App\Filament\Resources\Purchases\Actions;

use App\Models\Product;
use App\Models\ProductCategory;
use App\Models\Supplier;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Schemas\Components\Grid;
use Filament\Support\Enums\Width;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class QuickCreatePurchaseProductAction
{
    /**
     * Tras crear el producto, se invoca con el modelo para añadir la línea en el formulario de compra.
     *
     * @param  callable(Product $product, ?float $unitCostFromModal): void  $onCreated
     */
    public static function make(callable $onCreated): Action
    {
        return Action::make('quickCreatePurchaseProduct')
            ->label('')
            ->icon(Heroicon::Cube)
            // No usar ->hidden(true): Filament trata acciones ocultas como deshabilitadas y mountAction() no abre el modal.
            ->extraAttributes([
                'class' => 'hidden',
            ])
            ->modalWidth(Width::Large)
            ->modalHeading('Registrar producto')
            ->modalDescription('El artículo no estaba en el catálogo. Complete los datos mínimos; podrá ampliar la ficha después.')
            ->modalSubmitActionLabel('Crear y añadir a la compra')
            ->modalIcon(Heroicon::Cube)
            ->fillForm(function (Action $action): array {
                $args = $action->getArguments();
                $search = trim((string) ($args['search'] ?? ''));
                $supplierId = $args['supplier_id'] ?? null;
                $looksLikeBarcode = $search !== '' && preg_match('/^[0-9A-Za-z\-]{4,}$/', $search) === 1 && ! str_contains($search, ' ');

                return [
                    'name' => $looksLikeBarcode ? '' : $search,
                    'barcode' => $looksLikeBarcode ? $search : '',
                    'brand' => '',
                    'supplier_id' => filled($supplierId) ? (int) $supplierId : null,
                    'cost_price' => 0,
                    'applies_vat' => true,
                    'product_category_id' => null,
                    'requires_expiry_on_purchase' => true,
                ];
            })
            ->schema([
                Grid::make(1)
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre comercial')
                            ->required()
                            ->maxLength(255)
                            ->prefixIcon(Heroicon::ShoppingBag),
                        TextInput::make('barcode')
                            ->label('Código de barras / EAN')
                            ->maxLength(255)
                            ->unique(Product::class, 'barcode')
                            ->helperText('Opcional si no aplica. Debe ser único si lo indica.')
                            ->prefixIcon(Heroicon::QrCode)
                            ->dehydrateStateUsing(fn (?string $state): ?string => $state === '' || $state === null ? null : $state),
                        TextInput::make('brand')
                            ->label('Marca / laboratorio')
                            ->required()
                            ->maxLength(255)
                            ->prefixIcon(Heroicon::BuildingStorefront),
                        Select::make('supplier_id')
                            ->label('Proveedor principal')
                            ->options(fn (): array => Supplier::query()
                                ->where('is_active', true)
                                ->orderBy('legal_name')
                                ->get()
                                ->mapWithKeys(fn (Supplier $supplier): array => [
                                    $supplier->getKey() => ($supplier->trade_name ?: $supplier->legal_name)
                                        .(filled($supplier->tax_id) ? ' · '.$supplier->tax_id : ''),
                                ])
                                ->all())
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->placeholder('Sin proveedor')
                            ->prefixIcon(Heroicon::Truck),
                        Select::make('product_category_id')
                            ->label('Categoría')
                            ->options(fn (): array => ProductCategory::query()
                                ->where('is_active', true)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->native(false)
                            ->required()
                            ->prefixIcon(Heroicon::Swatch),
                        TextInput::make('cost_price')
                            ->label('Costo de compra')
                            ->numeric()
                            ->minValue(0)
                            ->step(0.00000001)
                            ->default(0)
                            ->prefix('$')
                            ->required()
                            ->rule('decimal:0,8')
                            ->prefixIcon(Heroicon::ReceiptPercent),
                        Toggle::make('applies_vat')
                            ->label('Grava IVA')
                            ->helperText('Si aplica, en la línea se sugerirá el IVA por defecto del sistema.')
                            ->default(true),
                        Toggle::make('requires_expiry_on_purchase')
                            ->label('Maneja lotes en compras')
                            ->default(true),
                    ]),
            ])
            ->action(function (array $data) use ($onCreated): void {
                $name = trim((string) ($data['name'] ?? ''));
                if ($name === '') {
                    throw ValidationException::withMessages([
                        'name' => 'Indique el nombre del producto.',
                    ]);
                }

                $actor = auth()->user()?->email
                    ?? auth()->user()?->name
                    ?? 'sistema';

                $barcode = $data['barcode'] ?? null;
                $barcode = filled($barcode) ? trim((string) $barcode) : null;
                $barcode = $barcode === '' ? null : $barcode;

                if ($barcode !== null && Product::query()->where('barcode', $barcode)->exists()) {
                    throw ValidationException::withMessages([
                        'barcode' => 'Ya existe un producto con ese código de barras.',
                    ]);
                }

                $sku = self::generateUniqueProductSku();

                $baseSlug = Str::slug($name);
                $slug = ($baseSlug !== '' ? $baseSlug : 'producto').'-'.Str::lower(Str::random(8));
                while (Product::query()->where('slug', $slug)->exists()) {
                    $slug = ($baseSlug !== '' ? $baseSlug : 'producto').'-'.Str::lower(Str::random(8));
                }

                $product = Product::query()->create([
                    'supplier_id' => filled($data['supplier_id'] ?? null) ? (int) $data['supplier_id'] : null,
                    'barcode' => $barcode,
                    'name' => $name,
                    'brand' => trim((string) ($data['brand'] ?? '')),
                    'slug' => $slug,
                    'product_category_id' => (int) $data['product_category_id'],
                    'cost_price' => (float) ($data['cost_price'] ?? 0),
                    'discount_percent' => 0,
                    'applies_vat' => (bool) ($data['applies_vat'] ?? true),
                    'requires_expiry_on_purchase' => (bool) ($data['requires_expiry_on_purchase'] ?? true),
                    'sku' => $sku,
                    'is_active' => true,
                    'created_by' => $actor,
                    'updated_by' => $actor,
                ]);

                if (blank($product->barcode)) {
                    $product->update([
                        'barcode' => '00'.$product->id,
                    ]);
                }

                $onCreated($product->fresh(), (float) ($data['cost_price'] ?? 0));

                Notification::make()
                    ->title('Producto creado')
                    ->body('Se añadió a las líneas de esta compra.')
                    ->success()
                    ->send();
            });
    }

    private static function generateUniqueProductSku(): string
    {
        do {
            $sku = 'SKU-'.now()->format('Ymd').'-'.Str::upper(Str::random(6));
        } while (Product::query()->where('sku', $sku)->exists());

        return $sku;
    }
}
