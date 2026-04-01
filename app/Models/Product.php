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
        'image',
        'product_type',
        'brand',
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
        'sku',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'product_type' => ProductType::class,
            'unit_content' => 'decimal:3',
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

    /**
     * Placeholder para la columna de imagen en tablas (SVG como data URI).
     */
    public function tableImagePlaceholderDataUri(): string
    {
        return self::initialsAvatarDataUri((string) $this->name);
    }

    /**
     * Genera un data URI SVG con iniciales derivadas del nombre (UTF-8).
     */
    public static function initialsAvatarDataUri(string $name, int $size = 88): string
    {
        $initials = self::initialsFromCommercialName($name);
        $escaped = htmlspecialchars($initials, ENT_QUOTES | ENT_XML1, 'UTF-8');
        $rx = (int) round($size * 0.22);
        $fontSize = (int) round($size * 0.34);

        $svg = <<<SVG
            <svg xmlns="http://www.w3.org/2000/svg" width="{$size}" height="{$size}" viewBox="0 0 {$size} {$size}">
            <rect width="100%" height="100%" fill="#e4e4e7" rx="{$rx}"/>
            <text x="50%" y="50%" dominant-baseline="central" text-anchor="middle" fill="#52525b" font-family="system-ui,-apple-system,sans-serif" font-size="{$fontSize}" font-weight="600">{$escaped}</text>
            </svg>
        SVG;

        return 'data:image/svg+xml,'.rawurlencode($svg);
    }

    /**
     * Iniciales para avatar: dos palabras → primera de cada una; una palabra → dos primeras letras; vacío → «?».
     */
    public static function initialsFromCommercialName(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            return '?';
        }

        $parts = preg_split('/\s+/u', $name, -1, PREG_SPLIT_NO_EMPTY);
        if ($parts === false || $parts === []) {
            return mb_strtoupper(mb_substr($name, 0, 1, 'UTF-8'), 'UTF-8');
        }

        if (count($parts) >= 2) {
            $first = mb_substr($parts[0], 0, 1, 'UTF-8');
            $last = mb_substr($parts[count($parts) - 1], 0, 1, 'UTF-8');

            return mb_strtoupper($first.$last, 'UTF-8');
        }

        $word = $parts[0];
        $len = mb_strlen($word, 'UTF-8');
        if ($len <= 1) {
            return mb_strtoupper($word, 'UTF-8');
        }

        return mb_strtoupper(mb_substr($word, 0, 2, 'UTF-8'), 'UTF-8');
    }
}
