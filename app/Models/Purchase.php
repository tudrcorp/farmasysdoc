<?php

namespace App\Models;

use App\Enums\PurchaseEntryCurrency;
use App\Enums\PurchaseStatus;
use App\Support\Purchases\LotExpirationMonthYear;
use App\Support\Purchases\PurchaseDocumentTotals;
use Database\Factories\PurchaseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Arr;
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
        'entry_currency',
        'official_usd_ves_rate',
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
        'declared_invoice_total',
        'supplier_invoice_number',
        'supplier_control_number',
        'supplier_invoice_date',
        'payment_due_date',
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
            'entry_currency' => PurchaseEntryCurrency::class,
            'ordered_at' => 'datetime',
            'expected_delivery_at' => 'datetime',
            'received_at' => 'datetime',
            'supplier_invoice_date' => 'date',
            'payment_due_date' => 'date',
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
            'declared_invoice_total' => 'decimal:2',
            'official_usd_ves_rate' => 'decimal:2',
        ];
    }

    public function entryCurrency(): PurchaseEntryCurrency
    {
        $c = $this->entry_currency;

        return $c instanceof PurchaseEntryCurrency ? $c : PurchaseEntryCurrency::USD;
    }

    public function documentMoneyPrefix(): string
    {
        return $this->entryCurrency()->moneyPrefix();
    }

    /**
     * Proveedor de la compra. Junto con {@see self::$supplier_invoice_number} forma el correlativo fiscal
     * único en base de datos (índice único `supplier_id` + `supplier_invoice_number`).
     *
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
     * Cuenta por pagar generada automáticamente si la compra quedó a crédito.
     *
     * @return HasOne<AccountsPayable, $this>
     */
    public function accountsPayable(): HasOne
    {
        return $this->hasOne(AccountsPayable::class);
    }

    /**
     * @return HasMany<PurchaseHistory, $this>
     */
    public function purchaseHistories(): HasMany
    {
        return $this->hasMany(PurchaseHistory::class)->orderByDesc('id');
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
     * Persiste en silencio los totales de encabezado calculados desde las líneas guardadas.
     *
     * Tras crear una compra, Filament puede persistir el padre antes que las líneas; el encabezado
     * quedaría en cero si el estado del formulario no incluía ítems al fusionar totales. Este método
     * corrige el registro una vez existen {@see PurchaseItem}.
     */
    public function syncDocumentHeaderTotalsFromItemsQuietly(): void
    {
        if (! $this->exists) {
            return;
        }

        $this->loadMissing(['items' => fn ($query) => $query->orderBy('line_number')->orderBy('id')]);

        if ($this->items->isEmpty()) {
            return;
        }

        $expected = $this->expectedHeaderTotalsFromItems();

        $this->forceFill(Arr::only($expected, [
            'subtotal_exempt_amount',
            'subtotal_taxable_amount',
            'subtotal',
            'discount_total',
            'document_discount_amount',
            'net_exempt_after_document_discount',
            'net_taxable_after_document_discount',
            'tax_total',
            'total',
        ]))->saveQuietly();
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
