<?php

namespace App\Models;

use App\Enums\PurchaseStatus;
use App\Support\Purchases\LotExpirationMonthYear;
use App\Support\Purchases\PurchaseDocumentTotals;
use Database\Factories\PurchaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Purchase extends Model
{
    /** @use HasFactory<PurchaseFactory> */
    use HasFactory;

    /**
     * Valor temporal único en INSERT; en {@see static::created} se sustituye por {@see self::composeAutoPurchaseNumber()}.
     */
    public const PENDING_PURCHASE_NUMBER_PREFIX = '__tmp_oc_';

    protected static function booted(): void
    {
        static::creating(function (Purchase $purchase): void {
            if (blank($purchase->purchase_number)) {
                $purchase->purchase_number = self::PENDING_PURCHASE_NUMBER_PREFIX.Str::uuid()->toString();
            }
        });

        static::created(function (Purchase $purchase): void {
            if (! str_starts_with((string) $purchase->purchase_number, self::PENDING_PURCHASE_NUMBER_PREFIX)) {
                return;
            }
            $purchase->updateQuietly([
                'purchase_number' => $purchase->composeAutoPurchaseNumber(),
            ]);
        });

        static::deleting(function (Purchase $purchase): void {
            $purchase->items()->each(function (PurchaseItem $item): void {
                $item->delete();
            });
        });
    }

    /**
     * Número visible: OC-{año de creación}-{id con 4 dígitos mínimo}, p. ej. OC-2026-0001.
     */
    public function composeAutoPurchaseNumber(): string
    {
        return sprintf(
            'OC-%s-%s',
            $this->created_at->format('Y'),
            str_pad((string) $this->getKey(), 4, '0', STR_PAD_LEFT),
        );
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
        'subtotal_exempt_amount',
        'subtotal_taxable_amount',
        'tax_total',
        'discount_total',
        'document_discount_percent',
        'document_discount_amount',
        'net_exempt_after_document_discount',
        'net_taxable_after_document_discount',
        'total',
        'supplier_invoice_number',
        'supplier_control_number',
        'supplier_invoice_date',
        'registered_in_system_date',
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
            'registered_in_system_date' => 'date',
            'subtotal' => 'decimal:2',
            'subtotal_exempt_amount' => 'decimal:2',
            'subtotal_taxable_amount' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'document_discount_percent' => 'decimal:2',
            'document_discount_amount' => 'decimal:2',
            'net_exempt_after_document_discount' => 'decimal:2',
            'net_taxable_after_document_discount' => 'decimal:2',
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
     * Lotes registrados a partir de líneas de compra con vencimiento mm/AAAA.
     *
     * @return HasMany<ProductLot, $this>
     */
    public function productLots(): HasMany
    {
        return $this->hasMany(ProductLot::class);
    }

    /**
     * Crea o actualiza filas en `product_lots` según `lot_expiration_month_year` de cada línea.
     */
    public function syncProductLotsFromItems(): void
    {
        $invoiceRef = trim((string) ($this->supplier_invoice_number ?? ''));
        if ($invoiceRef === '') {
            $invoiceRef = (string) $this->purchase_number;
        }

        $this->loadMissing('items');

        foreach ($this->items as $item) {
            $exp = LotExpirationMonthYear::normalize($item->lot_expiration_month_year ?? null);

            if ($exp === null || ! LotExpirationMonthYear::isValidFormat($exp)) {
                ProductLot::query()->where('purchase_item_id', $item->id)->delete();

                continue;
            }

            ProductLot::query()->updateOrCreate(
                ['purchase_item_id' => $item->id],
                [
                    'purchase_id' => $this->id,
                    'product_id' => $item->product_id,
                    'supplier_invoice_number' => $invoiceRef,
                    'expiration_month_year' => $exp,
                ],
            );
        }
    }

    /**
     * Agregación solo por líneas (descuentos en línea, sin descuento documento ni recalculo de IVA global).
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
     * Totales de encabezado esperados desde líneas + % descuento documento persistido.
     *
     * @return array<string, float>
     */
    public function expectedHeaderTotalsFromItems(): array
    {
        $lines = $this->relationLoaded('items')
            ? $this->items
            : $this->items()->orderBy('line_number')->orderBy('id')->get();

        $state = $lines->map(fn (PurchaseItem $line): array => $line->toDocumentTotalsState())->values()->all();

        return PurchaseDocumentTotals::documentHeaderWithDocumentDiscount($state, (float) $this->document_discount_percent);
    }

    /**
     * Indica si los montos del encabezado coinciden con líneas y descuento documento.
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
                && abs((float) $this->discount_total) <= $epsilon
                && abs((float) $this->subtotal_exempt_amount) <= $epsilon
                && abs((float) $this->subtotal_taxable_amount) <= $epsilon
                && abs((float) $this->document_discount_amount) <= $epsilon
                && abs((float) $this->net_exempt_after_document_discount) <= $epsilon
                && abs((float) $this->net_taxable_after_document_discount) <= $epsilon;
        }

        $expected = $this->expectedHeaderTotalsFromItems();

        $fields = [
            'subtotal',
            'subtotal_exempt_amount',
            'subtotal_taxable_amount',
            'discount_total',
            'document_discount_percent',
            'document_discount_amount',
            'net_exempt_after_document_discount',
            'net_taxable_after_document_discount',
            'tax_total',
            'total',
        ];

        foreach ($fields as $field) {
            if (abs((float) $this->{$field} - (float) ($expected[$field] ?? 0.0)) > $epsilon) {
                return false;
            }
        }

        return true;
    }
}
