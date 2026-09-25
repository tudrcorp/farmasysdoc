<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MedicationQuoteLine extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'medication_quote_id',
        'product_id',
        'description',
        'quantity',
        'unit_price_usd',
        'line_total_usd',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:3',
            'unit_price_usd' => 'decimal:2',
            'line_total_usd' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (MedicationQuoteLine $line): void {
            $quantity = max(0, (float) $line->quantity);
            $unitPrice = max(0, (float) $line->unit_price_usd);
            $line->line_total_usd = round($quantity * $unitPrice, 2);

            if (blank($line->description) && $line->product_id !== null) {
                $line->description = (string) (Product::query()->whereKey($line->product_id)->value('name') ?: 'Medicamento');
            }
        });
    }

    /**
     * @return BelongsTo<MedicationQuote, $this>
     */
    public function quote(): BelongsTo
    {
        return $this->belongsTo(MedicationQuote::class, 'medication_quote_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
