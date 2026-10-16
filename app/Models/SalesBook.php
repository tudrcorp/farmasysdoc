<?php

namespace App\Models;

use App\Enums\SalesBookStatus;
use App\Support\Fiscal\SalesBookPeriod;
use Database\Factories\SalesBookFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use LogicException;

/**
 * Libro de Ventas de una sucursal y quincena, congelado al cerrarlo. Solo se puede marcar como reabierto
 * (queda como versión anulada en el historial); su contenido nunca cambia.
 */
class SalesBook extends Model
{
    /** @use HasFactory<SalesBookFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    private const MUTABLE_ON_REOPEN = ['status', 'reopened_by', 'reopened_at', 'reopen_reason', 'updated_at'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'branch_id',
        'year',
        'month',
        'half',
        'period_start',
        'period_end',
        'version',
        'status',
        'snapshot',
        'content_hash',
        'z_reports_count',
        'total_sales_ves',
        'exempt_ves',
        'taxable_base_ves',
        'tax_ves',
        'retained_ves',
        'closed_by',
        'closed_at',
        'reopened_by',
        'reopened_at',
        'reopen_reason',
    ];

    protected static function booted(): void
    {
        static::updating(function (SalesBook $book): void {
            $changed = array_diff(array_keys($book->getDirty()), self::MUTABLE_ON_REOPEN);

            if ($changed !== [] || $book->getOriginal('status') !== SalesBookStatus::Closed) {
                throw new LogicException('Un Libro de Ventas cerrado no se puede modificar; solo reabrir.');
            }
        });

        static::deleting(function (): void {
            throw new LogicException('Un Libro de Ventas cerrado no se puede eliminar.');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'year' => 'integer',
            'month' => 'integer',
            'half' => 'integer',
            'version' => 'integer',
            'period_start' => 'date',
            'period_end' => 'date',
            'status' => SalesBookStatus::class,
            'z_reports_count' => 'integer',
            'total_sales_ves' => 'decimal:2',
            'exempt_ves' => 'decimal:2',
            'taxable_base_ves' => 'decimal:2',
            'tax_ves' => 'decimal:2',
            'retained_ves' => 'decimal:2',
            'closed_at' => 'datetime',
            'reopened_at' => 'datetime',
        ];
    }

    public function period(): SalesBookPeriod
    {
        return new SalesBookPeriod($this->year, $this->month, $this->half);
    }

    /**
     * @return array<string, mixed>
     */
    public function snapshotData(): array
    {
        $decoded = json_decode((string) $this->snapshot, true);

        return is_array($decoded) ? $decoded : [];
    }

    public function hasValidContentHash(): bool
    {
        return hash_equals((string) $this->content_hash, hash('sha256', (string) $this->snapshot));
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
