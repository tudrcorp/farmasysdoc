<?php

namespace App\Models;

use Database\Factories\PurchaseItemFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Línea de detalle de una compra / factura de proveedor (ítems persistidos para informes, impresión y auditoría).
 */
class PurchaseItem extends Model
{
    /** @use HasFactory<PurchaseItemFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'purchase_id',
        'line_number',
        'product_id',
        'inventory_id',
        'quantity_ordered',
        'quantity_received',
        'unit_cost',
        'line_discount_percent',
        'line_vat_percent',
        'line_subtotal',
        'tax_amount',
        'line_total',
        'product_name_snapshot',
        'sku_snapshot',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'line_number' => 'integer',
            'quantity_ordered' => 'decimal:3',
            'quantity_received' => 'decimal:3',
            'unit_cost' => 'decimal:4',
            'line_discount_percent' => 'decimal:2',
            'line_vat_percent' => 'decimal:2',
            'line_subtotal' => 'decimal:2',
            'tax_amount' => 'decimal:2',
            'line_total' => 'decimal:2',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (PurchaseItem $item): void {
            if ($item->line_number !== null) {
                return;
            }
            $purchaseId = $item->purchase_id;
            if ($purchaseId === null || (int) $purchaseId <= 0) {
                return;
            }
            $next = (int) static::query()->where('purchase_id', $purchaseId)->max('line_number');

            $item->line_number = $next + 1;
        });
    }

    /**
     * Estado de la línea para recalcular totales del documento (validación e informes).
     *
     * @return array<string, mixed>
     */
    public function toDocumentTotalsState(): array
    {
        return [
            'quantity_ordered' => $this->quantity_ordered,
            'unit_cost' => $this->unit_cost,
            'line_discount_percent' => $this->line_discount_percent,
            'line_vat_percent' => $this->line_vat_percent,
        ];
    }

    /**
     * Etiqueta legible para reportes (nombre y código al momento de la compra).
     */
    public function getInvoiceLineLabelAttribute(): string
    {
        $name = trim((string) $this->product_name_snapshot);
        $code = trim((string) $this->sku_snapshot);

        if ($name !== '' && $code !== '') {
            return $name.' · '.$code;
        }

        return $name !== '' ? $name : $code;
    }

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return BelongsTo<Inventory, $this>
     */
    public function inventory(): BelongsTo
    {
        return $this->belongsTo(Inventory::class);
    }
}
