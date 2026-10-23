<?php

namespace App\Models;

use App\Enums\EmployeeBankAccountType;
use App\Enums\PettyCashCurrency;
use App\Enums\PettyCashFundStatus;
use App\Enums\VenezuelanPagoMovilBank;
use Database\Factories\PettyCashFundFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PettyCashFund extends Model
{
    /** @use HasFactory<PettyCashFundFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'branch_id',
        'replenished_from_fund_id',
        'superseded_by_fund_id',
        'requested_by',
        'requested_at',
        'notes',
        'requested_currency',
        'requested_amount',
        'amount_usd',
        'amount_ves',
        'carried_over_usd',
        'carried_over_ves',
        'bcv_ves_per_usd',
        'remaining_usd',
        'remaining_ves',
        'bank_code',
        'bank_account_type',
        'bank_account_number',
        'bank_account_holder',
        'holder_phone',
        'holder_national_id',
        'status',
        'approved_by',
        'approved_at',
        'payment_proof_path',
        'payment_reference',
        'rejection_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requested_at' => 'date',
            'requested_currency' => PettyCashCurrency::class,
            'requested_amount' => 'decimal:2',
            'amount_usd' => 'decimal:2',
            'amount_ves' => 'decimal:2',
            'carried_over_usd' => 'decimal:2',
            'carried_over_ves' => 'decimal:2',
            'bcv_ves_per_usd' => 'decimal:6',
            'remaining_usd' => 'decimal:2',
            'remaining_ves' => 'decimal:2',
            'bank_account_type' => EmployeeBankAccountType::class,
            'status' => PettyCashFundStatus::class,
            'approved_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<PettyCashFund, $this>
     */
    public function replenishedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'replenished_from_fund_id');
    }

    /**
     * @return BelongsTo<PettyCashFund, $this>
     */
    public function supersededBy(): BelongsTo
    {
        return $this->belongsTo(self::class, 'superseded_by_fund_id');
    }

    public function canReplenish(): bool
    {
        return $this->status === PettyCashFundStatus::Approved
            && (float) $this->remaining_usd > 0.009;
    }

    public function isReplenishment(): bool
    {
        return $this->replenished_from_fund_id !== null;
    }

    public function withReplenishmentQualifier(string $label): string
    {
        if (! $this->isReplenishment()) {
            return $label;
        }

        return $label.' (Reposición)';
    }

    /** Monto que administración debe transferir (reposición: solo el monto nuevo). */
    public function transferAmountUsd(): float
    {
        if (! $this->isReplenishment()) {
            return (float) $this->amount_usd;
        }

        return max(0, round((float) $this->amount_usd - (float) $this->carried_over_usd, 2));
    }

    /** Monto que administración debe transferir (reposición: solo el monto nuevo). */
    public function transferAmountVes(): float
    {
        if (! $this->isReplenishment()) {
            return (float) $this->amount_ves;
        }

        return max(0, round((float) $this->amount_ves - (float) $this->carried_over_ves, 2));
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approvedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<PettyCashExpense, $this>
     */
    public function expenses(): HasMany
    {
        return $this->hasMany(PettyCashExpense::class)->orderByDesc('spent_at')->orderByDesc('id');
    }

    public function isOpen(): bool
    {
        return $this->status instanceof PettyCashFundStatus && $this->status->isOpen();
    }

    public function canRegisterExpenses(): bool
    {
        return $this->status === PettyCashFundStatus::Approved
            && (float) $this->remaining_usd > 0.009;
    }

    public function bankLabel(): string
    {
        $bank = VenezuelanPagoMovilBank::tryFrom((string) $this->bank_code);

        return $bank instanceof VenezuelanPagoMovilBank
            ? $bank->optionLabel()
            : (string) $this->bank_code;
    }

    public function bankAccountSummary(): string
    {
        $parts = [$this->bankLabel()];

        $type = $this->bank_account_type instanceof EmployeeBankAccountType
            ? $this->bank_account_type->label()
            : (filled($this->bank_account_type) ? (string) $this->bank_account_type : null);

        if (filled($type)) {
            $parts[] = $type;
        }

        if (filled($this->bank_account_number)) {
            $parts[] = (string) $this->bank_account_number;
        }

        return implode(' · ', $parts);
    }
}
