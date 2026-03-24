<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

class OrderService extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'service_order_number',
        'partner_company_id',
        'client_id',
        'branch_id',
        'status',
        'priority',
        'service_type',
        'authorization_reference',
        'external_reference',
        'patient_name',
        'patient_document',
        'patient_phone',
        'patient_email',
        'ordered_at',
        'scheduled_at',
        'started_at',
        'completed_at',
        'subtotal',
        'tax_total',
        'discount_total',
        'total',
        'diagnosis',
        'notes',
        'items',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'ordered_at' => 'datetime',
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'subtotal' => 'decimal:2',
            'tax_total' => 'decimal:2',
            'discount_total' => 'decimal:2',
            'total' => 'decimal:2',
            'items' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (OrderService $order): void {
            $order->items = $order->normalizeMedicationItems($order->items);
        });
    }

    /**
     * Normaliza el listado de medicamentos: cada fila queda como
     * `position` (1..n), `name` (texto no vacío) e `indicacion` (texto, puede quedar vacío si es legado).
     *
     * @param  array<int, mixed>|null  $raw
     * @return list<array{position: int, name: string, indicacion: string}>
     */
    public function normalizeMedicationItems(?array $raw): array
    {
        if ($raw === null || $raw === []) {
            return [];
        }

        /** @var Collection<int, array{name: string, indicacion: string}> $rows */
        $rows = collect($raw)
            ->map(function (mixed $row): array {
                if (is_string($row)) {
                    return [
                        'name' => trim($row),
                        'indicacion' => '',
                    ];
                }

                if (is_array($row)) {
                    return [
                        'name' => trim((string) ($row['name'] ?? '')),
                        'indicacion' => trim((string) ($row['indicacion'] ?? '')),
                    ];
                }

                return [
                    'name' => '',
                    'indicacion' => '',
                ];
            })
            ->filter(fn (array $row): bool => $row['name'] !== '')
            ->values();

        return $rows
            ->map(fn (array $row, int $index): array => [
                'position' => $index + 1,
                'name' => $row['name'],
                'indicacion' => $row['indicacion'],
            ])
            ->all();
    }

    /**
     * Consecutivo visible de la orden: prefijo ORD-00 + id del registro.
     */
    public static function formatServiceOrderNumber(int|string $id): string
    {
        return 'ORD-00'.$id;
    }

    /**
     * @return BelongsTo<PartnerCompany, $this>
     */
    public function partnerCompany(): BelongsTo
    {
        return $this->belongsTo(PartnerCompany::class);
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * Líneas detalladas de la orden (precios, productos vinculados, etc.).
     *
     * @return HasMany<OrderServiceItem, $this>
     */
    public function serviceItems(): HasMany
    {
        return $this->hasMany(OrderServiceItem::class, 'order_service_id');
    }
}
