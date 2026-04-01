<?php

namespace App\Models;

use App\Enums\ProductType;
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
        'sale_price',
        'cost_price',
        'tax_rate',
        'discount_percent',
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
        'product_type',
        'active_ingredient',
        'concentration',
        'presentation_type',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sale_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'discount_percent' => 'decimal:2',
            'quantity' => 'decimal:3',
            'reserved_quantity' => 'decimal:3',
            'reorder_point' => 'decimal:3',
            'minimum_stock' => 'decimal:3',
            'maximum_stock' => 'decimal:3',
            'allow_negative_stock' => 'boolean',
            'last_movement_at' => 'datetime',
            'last_stock_take_at' => 'datetime',
            'product_type' => ProductType::class,
            'active_ingredient' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (Inventory $inventory): void {
            $inventory->syncPharmacySnapshotFromRelatedProduct();
        });

        static::updating(function (Inventory $inventory): void {
            if ($inventory->isDirty('product_id')) {
                $inventory->syncPharmacySnapshotFromRelatedProduct();
            }
        });
    }

    /**
     * Valores de catálogo del producto para persistir en la fila de inventario (snapshot).
     *
     * @return array{product_type: ?string, active_ingredient: ?array<int, string>, concentration: ?string, presentation_type: ?string}
     */
    public static function pharmacySnapshotFromProduct(Product $product): array
    {
        $type = $product->product_type;
        $isMedication = $type === ProductType::Medication;
        $activeIngredients = $isMedication && is_array($product->active_ingredient)
            ? array_values(array_filter($product->active_ingredient, fn (mixed $item): bool => is_string($item) && filled($item)))
            : null;

        return [
            'product_type' => $type instanceof \BackedEnum ? $type->value : $type,
            'active_ingredient' => $activeIngredients,
            'concentration' => $product->concentration,
            'presentation_type' => $product->presentation_type,
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
     * Precio unitario de venta tras aplicar el descuento % de la sucursal (antes de impuesto).
     */
    public function effectiveSaleUnitPrice(): float
    {
        $list = (float) $this->sale_price;
        $pct = max(0.0, min(100.0, (float) $this->discount_percent));

        return round($list * (1 - $pct / 100), 2);
    }

    /**
     * Valor monetario del descuento de línea (lista − efectivo) para una cantidad dada.
     */
    public function monetaryLineDiscountForQuantity(float $quantity): float
    {
        $list = (float) $this->sale_price;
        $pct = max(0.0, min(100.0, (float) $this->discount_percent));

        return round($quantity * $list * ($pct / 100), 2);
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
