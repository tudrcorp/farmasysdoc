<?php

namespace App\Models;

use App\Support\Finance\DefaultVatRate;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Inventory extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'branch_id',
        'product_id',
        'quantity',
        'reserved_quantity',
        'reorder_point',
        'minimum_stock',
        'maximum_stock',
        'storage_location',
        'allow_negative_stock',
        'last_movement_at',
        'last_stock_take_at',
        'notes',
        'created_by',
        'updated_by',
        'product_category_id',
        'active_ingredient',
        'concentration',
        'presentation_type',
        'cost_price',
        'vat_cost_amount',
        'cost_plus_vat',
        'final_price_without_vat',
        'vat_final_price_amount',
        'final_price_with_vat',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'reserved_quantity' => 'decimal:3',
            'reorder_point' => 'decimal:3',
            'minimum_stock' => 'decimal:3',
            'maximum_stock' => 'decimal:3',
            'allow_negative_stock' => 'boolean',
            'last_movement_at' => 'datetime',
            'last_stock_take_at' => 'datetime',
            'active_ingredient' => 'array',
            'cost_price' => 'decimal:8',
            'vat_cost_amount' => 'decimal:8',
            'cost_plus_vat' => 'decimal:8',
            'final_price_without_vat' => 'decimal:8',
            'vat_final_price_amount' => 'decimal:8',
            'final_price_with_vat' => 'decimal:8',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Inventory $inventory): void {
            $inventory->syncPharmacySnapshotFromRelatedProduct();
            $inventory->syncFinancialSnapshotFromRelatedProductAndCost();
        });

        static::updating(function (Inventory $inventory): void {
            if ($inventory->isDirty('product_id') || $inventory->isDirty('cost_price')) {
                $inventory->syncPharmacySnapshotFromRelatedProduct();
                $inventory->syncFinancialSnapshotFromRelatedProductAndCost();
            }
        });
    }

    /**
     * Valores de catálogo del producto para persistir en la fila de inventario (snapshot).
     *
     * @return array{product_category_id: ?int, active_ingredient: ?array<int, string>, concentration: ?string, presentation_type: ?string}
     */
    public static function pharmacySnapshotFromProduct(Product $product): array
    {
        $product->loadMissing('productCategory');
        $isMedication = (bool) ($product->productCategory?->is_medication ?? false);
        $activeIngredients = $isMedication && is_array($product->active_ingredient)
            ? array_values(array_filter($product->active_ingredient, fn (mixed $item): bool => is_string($item) && filled($item)))
            : null;

        return [
            'product_category_id' => $product->product_category_id !== null ? (int) $product->product_category_id : null,
            'active_ingredient' => $activeIngredients,
            'concentration' => $product->concentration,
            'presentation_type' => $product->presentation_type,
        ];
    }

    /**
     * Snapshot financiero persistido en inventario usando el costo y la categoría del producto.
     *
     * @return array{
     *     cost_price: float,
     *     vat_cost_amount: float,
     *     cost_plus_vat: float,
     *     final_price_without_vat: float,
     *     vat_final_price_amount: float,
     *     final_price_with_vat: float
     * }
     */
    public static function financialSnapshotFromCostAndProduct(float $cost, ?Product $product): array
    {
        $safeCost = round(max(0.0, $cost), 8);
        $vatRate = 0.0;
        if ($product !== null && $product->applies_vat) {
            $vatRate = max(0.0, DefaultVatRate::percent());
        }

        $profitPercent = 0.0;
        if ($product !== null) {
            $product->loadMissing('productCategory');
            $category = $product->productCategory;
            if ($category !== null && (bool) $category->is_active) {
                $profitPercent = max(0.0, (float) $category->profit_percentage);
            }
        }

        $vatCostAmount = round($safeCost * ($vatRate / 100), 8);
        $costPlusVat = round($safeCost + $vatCostAmount, 8);
        $finalPriceWithoutVat = round($safeCost + ($safeCost * $profitPercent / 100), 8);
        $vatFinalPriceAmount = round($finalPriceWithoutVat * ($vatRate / 100), 8);
        $finalPriceWithVat = round($finalPriceWithoutVat + $vatFinalPriceAmount, 8);

        return [
            'cost_price' => $safeCost,
            'vat_cost_amount' => $vatCostAmount,
            'cost_plus_vat' => $costPlusVat,
            'final_price_without_vat' => $finalPriceWithoutVat,
            'vat_final_price_amount' => $vatFinalPriceAmount,
            'final_price_with_vat' => $finalPriceWithVat,
        ];
    }

    public function syncPharmacySnapshotFromRelatedProduct(): void
    {
        if ($this->product_id === null) {
            return;
        }

        $product = Product::query()->find($this->product_id);

        if ($product === null) {
            return;
        }

        foreach (self::pharmacySnapshotFromProduct($product) as $key => $value) {
            $this->setAttribute($key, $value);
        }
    }

    public function syncFinancialSnapshotFromRelatedProductAndCost(): void
    {
        if ($this->product_id === null) {
            return;
        }

        $product = Product::query()->find($this->product_id);
        $cost = (float) ($this->cost_price ?? ($product?->cost_price ?? 0));

        foreach (self::financialSnapshotFromCostAndProduct($cost, $product) as $key => $value) {
            $this->setAttribute($key, $value);
        }
    }

    /**
     * Cantidad disponible para venta (existencias menos reservado).
     *
     * @return Attribute<float, never>
     */
    protected function availableQuantity(): Attribute
    {
        return Attribute::get(function (): float {
            $available = (float) $this->quantity - (float) $this->reserved_quantity;

            if (! $this->allow_negative_stock) {
                return max(0.0, $available);
            }

            return $available;
        });
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Categoría de catálogo copiada del producto al crear o cambiar de producto (snapshot).
     *
     * @return BelongsTo<ProductCategory, $this>
     */
    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }

    /**
     * @return HasMany<InventoryMovement, $this>
     */
    public function movements(): HasMany
    {
        return $this->hasMany(InventoryMovement::class);
    }

    /**
     * @return HasMany<SaleItem, $this>
     */
    public function saleItems(): HasMany
    {
        return $this->hasMany(SaleItem::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function orderItems(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<PurchaseItem, $this>
     */
    public function purchaseItems(): HasMany
    {
        return $this->hasMany(PurchaseItem::class);
    }
}
