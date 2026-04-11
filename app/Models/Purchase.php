<?php

namespace App\Models;

use App\Enums\PurchaseStatus;
use App\Support\Purchases\PurchaseDocumentTotals;
use Database\Factories\PurchaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Purchase extends Model
{
    /** @use HasFactory<PurchaseFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::deleting(function (Purchase $purchase): void {
            $purchase->items()->each(function (PurchaseItem $item): void {
                $item->delete();
            });
        });
    }

    /**
     * @var list<string>
     */
    protected $fillable = [
        'purchase_number',
        'supplier_id',
        'branch_id',
        'status',
        'ordered_at',
        'expected_delivery_at',
        'received_at',
        'subtotal',
        'tax_total',
        'discount_total',
        'total',
        'supplier_invoice_number',
        'supplier_control_number',
        'supplier_invoice_date',
        'payment_status',
        'notes',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PurchaseStatus::class,
            'ordered_at' => 'datetime',
            'expected_delivery_at' => 'datetime',
            'received_at' => 'datetime',
            'supplier_invoice_date' => 'date',
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Líneas de detalle persistidas (orden adecuado para impresión e informes).
     *
     * @return HasMany<PurchaseItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PurchaseItem::class)
            ->orderBy('line_number')
            ->orderBy('id');
    }

    /**
     * Totales del documento calculados solo a partir de las líneas guardadas en base de datos.
     *
     * @return array{subtotal: float, tax_total: float, discount_total: float, total: float}
     */
    public function aggregatedTotalsFromItems(): array
    {
        $lines = $this->relationLoaded('items')
            ? $this->items
            : $this->items()->orderBy('line_number')->orderBy('id')->get();

        $state = $lines->map(fn (PurchaseItem $line): array => $line->toDocumentTotalsState())->values()->all();

        return PurchaseDocumentTotals::documentTotals($state);
    }

    /**
     * Indica si los montos del encabezado coinciden con la agregación de las líneas (validación de factura).
     */
    public function documentTotalsMatchStoredItems(float $epsilon = 0.02): bool
    {
        if (! $this->exists) {
            return true;
        }

        if (! $this->items()->exists()) {
            return abs((float) $this->total) <= $epsilon
                && abs((float) $this->subtotal) <= $epsilon
                && abs((float) $this->tax_total) <= $epsilon
                && abs((float) $this->discount_total) <= $epsilon;
        }

        $agg = $this->aggregatedTotalsFromItems();

        return abs((float) $this->subtotal - $agg['subtotal']) <= $epsilon
            && abs((float) $this->tax_total - $agg['tax_total']) <= $epsilon
            && abs((float) $this->discount_total - $agg['discount_total']) <= $epsilon
            && abs((float) $this->total - $agg['total']) <= $epsilon;
    }
}
