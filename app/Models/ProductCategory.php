<?php

namespace App\Models;

use App\Services\Pricing\BranchCategoryProfitMarginProvisioner;
use Database\Factories\ProductCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class ProductCategory extends Model
{
    /** @use HasFactory<ProductCategoryFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_medication' => 'boolean',
            'profit_percentage' => 'decimal:4',
        ];
    }

    protected $fillable = [
        'name',
        'description',
        'image',
        'slug',
        'is_active',
        'is_medication',
        'profit_percentage',
        'created_by',
        'updated_by',
    ];

    protected static function booted(): void
    {
        static::created(function (ProductCategory $category): void {
            $actorId = Auth::id();
            $actor = $actorId !== null ? (string) $actorId : 'sistema';

            app(BranchCategoryProfitMarginProvisioner::class)->provisionForCategory($category, $actor);
        });
    }

    /**
     * @return HasMany<Product, $this>
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    /**
     * @return HasMany<BranchCategoryProfitMargin, $this>
     */
    public function branchProfitMargins(): HasMany
    {
        return $this->hasMany(BranchCategoryProfitMargin::class, 'product_category_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
