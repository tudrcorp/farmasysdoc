<?php

namespace App\Models;

use App\Services\Pricing\BranchCategoryInventoryPriceRecalculator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class BranchCategoryProfitMargin extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'branch_id',
        'product_category_id',
        'profit_percentage',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'profit_percentage' => 'decimal:4',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (BranchCategoryProfitMargin $margin): void {
            if (! $margin->wasChanged('profit_percentage')) {
                return;
            }

            app(BranchCategoryInventoryPriceRecalculator::class)->recalculateForBranchAndCategory(
                (int) $margin->branch_id,
                (int) $margin->product_category_id,
            );
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
     * @return BelongsTo<ProductCategory, $this>
     */
    public function productCategory(): BelongsTo
    {
        return $this->belongsTo(ProductCategory::class, 'product_category_id');
    }
}
