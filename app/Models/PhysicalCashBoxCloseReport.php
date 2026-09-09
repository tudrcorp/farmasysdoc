<?php

namespace App\Models;

use Database\Factories\PhysicalCashBoxCloseReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reporte comparativo persistido al cerrar una caja física.
 *
 * @property-read PhysicalCashBox $physicalCashBox
 * @property-read User $user
 * @property-read Branch|null $branch
 */
class PhysicalCashBoxCloseReport extends Model
{
    /** @use HasFactory<PhysicalCashBoxCloseReportFactory> */
    use HasFactory;

    protected $table = 'cajas_fisicas_cierre_reportes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'physical_cash_box_id',
        'user_id',
        'branch_id',
        'opened_at',
        'closed_at',
        'declared_usd',
        'declared_ves',
        'expected_usd',
        'expected_ves',
        'difference_usd',
        'difference_ves',
        'pos_declared_ves',
        'pos_system_ves',
        'pos_difference_ves',
        'has_cash_mismatch',
        'has_pos_mismatch',
        'has_mismatch',
        'pos_lines',
        'report_snapshot',
        'pdf_path',
        'close_usd_cash_photo_path',
        'close_pos_receipt_photo_path',
        'whatsapp_sent_at',
        'email_sent_at',
        'whatsapp_error',
        'email_error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'declared_usd' => 'decimal:2',
            'declared_ves' => 'decimal:2',
            'expected_usd' => 'decimal:2',
            'expected_ves' => 'decimal:2',
            'difference_usd' => 'decimal:2',
            'difference_ves' => 'decimal:2',
            'pos_declared_ves' => 'decimal:2',
            'pos_system_ves' => 'decimal:2',
            'pos_difference_ves' => 'decimal:2',
            'has_cash_mismatch' => 'boolean',
            'has_pos_mismatch' => 'boolean',
            'has_mismatch' => 'boolean',
            'pos_lines' => 'array',
            'report_snapshot' => 'array',
            'whatsapp_sent_at' => 'datetime',
            'email_sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<PhysicalCashBox, $this>
     */
    public function physicalCashBox(): BelongsTo
    {
        return $this->belongsTo(PhysicalCashBox::class, 'physical_cash_box_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }
}
