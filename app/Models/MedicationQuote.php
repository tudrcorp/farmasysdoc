<?php

namespace App\Models;

use App\Enums\MedicationQuoteAdjustment;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

class MedicationQuote extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'number',
        'requester_name',
        'requester_document',
        'requester_phone',
        'requester_email',
        'adjustment_kind',
        'adjustment_percent',
        'subtotal_usd',
        'total_usd',
        'notes',
        'created_by',
        'emailed_at',
        'whatsapped_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'adjustment_kind' => MedicationQuoteAdjustment::class,
            'adjustment_percent' => 'decimal:2',
            'subtotal_usd' => 'decimal:2',
            'total_usd' => 'decimal:2',
            'emailed_at' => 'datetime',
            'whatsapped_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (MedicationQuote $quote): void {
            if (blank($quote->number)) {
                $quote->number = self::nextNumber();
            }

            if ($quote->created_by === null && Auth::id() !== null) {
                $quote->created_by = Auth::id();
            }
        });
    }

    /**
     * @return HasMany<MedicationQuoteLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(MedicationQuoteLine::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function fileName(): string
    {
        return 'cotizacion-'.$this->number.'.pdf';
    }

    public static function nextNumber(): string
    {
        $prefix = 'COT-'.now()->format('Ymd').'-';
        $last = self::query()
            ->where('number', 'like', $prefix.'%')
            ->orderByDesc('number')
            ->value('number');

        $sequence = 1;
        if (is_string($last) && preg_match('/(\d+)$/', $last, $matches) === 1) {
            $sequence = ((int) $matches[1]) + 1;
        }

        return $prefix.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT);
    }
}
