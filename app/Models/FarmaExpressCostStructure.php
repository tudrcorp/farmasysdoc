<?php

namespace App\Models;

use App\Services\Pricing\FarmaExpressBranchPriceSynchronizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FarmaExpressCostStructure extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'branch_id',
        'profit_percentage',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'profit_percentage' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saved(function (): void {
            app(FarmaExpressBranchPriceSynchronizer::class)->syncAllProducts();
        });

        static::deleted(function (): void {
            app(FarmaExpressBranchPriceSynchronizer::class)->syncAllProducts();
        });
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
