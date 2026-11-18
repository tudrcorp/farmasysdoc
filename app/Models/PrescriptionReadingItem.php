<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionReadingItem extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'prescription_reading_id',
        'medication_name',
        'concentration',
        'presentation',
        'quantity_text',
        'dosage_instructions',
        'confidence',
        'product_id',
        'is_controlled_substance',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'confidence' => 'decimal:2',
            'is_controlled_substance' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<PrescriptionReading, $this>
     */
    public function reading(): BelongsTo
    {
        return $this->belongsTo(PrescriptionReading::class, 'prescription_reading_id');
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
