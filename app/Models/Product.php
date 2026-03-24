<?php

namespace App\Models;

use App\Enums\ProductType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Product extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'supplier_id',
        'barcode',
        'name',
        'slug',
        'description',
        'product_type',
        'brand',
        'sale_price',
        'cost_price',
        'tax_rate',
        'active_ingredient',
        'concentration',
        'presentation_type',
        'requires_prescription',
        'is_controlled_substance',
        'ingredients',
        'allergens',
        'nutritional_information',
        'manufacturer',
        'model',
        'warranty_months',
        'medical_device_class',
        'requires_calibration',
        'storage_conditions',
        'is_active',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_type' => ProductType::class,
            'unit_content' => 'decimal:3',
            'sale_price' => 'decimal:2',
            'cost_price' => 'decimal:2',
            'tax_rate' => 'decimal:2',
            'requires_prescription' => 'boolean',
            'is_controlled_substance' => 'boolean',
            'requires_calibration' => 'boolean',
            'is_active' => 'boolean',
            'warranty_months' => 'integer',
            'active_ingredient' => 'array',
        ];
    }

    /**
     * Proveedor principal asociado al producto (opcional).
     *
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * Coincidencia parcial del principio activo (sin distinguir mayúsculas; escapa comodines SQL).
     *
     * @param  Builder<Product>  $query
     * @return Builder<Product>
     */
    public function scopeWhereActiveIngredientContains(Builder $query, string $term): Builder
    {
        $like = '%'.addcslashes(mb_strtolower(trim($term)), '%_\\').'%';

        return $query->whereRaw('LOWER(active_ingredient) LIKE ?', [$like]);
    }

    /**
     * Existencias del producto por sucursal (un registro por producto y sucursal).
     *
     * @return HasMany<Inventory, $this>
     */
    public function inventories(): HasMany
    {
        return $this->hasMany(Inventory::class);
    }

    /**
     * Inventario del producto en una sucursal concreta.
     */
    public function inventoryForBranch(Branch $branch): ?Inventory
    {
        return $this->inventories()->where('branch_id', $branch->id)->first();
    }

    /**
     * Historial de movimientos de inventario (entradas, salidas, ajustes).
     *
     * @return HasMany<InventoryMovement, $this>
     */
    public function inventoryMovements(): HasMany
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
