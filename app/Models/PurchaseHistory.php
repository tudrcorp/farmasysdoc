<?php

namespace App\Models;

use App\Enums\PaymentBankAccountType;
use App\Enums\VenezuelanPagoMovilBank;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PurchaseHistory extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'entry_type',
        'purchase_id',
        'branch_id',
        'accounts_payable_id',
        'issued_at',
        'registered_in_system_date',
        'supplier_invoice_number',
        'supplier_control_number',
        'supplier_tax_id',
        'supplier_name',
        'retention_voucher_issued_at',
        'retention_voucher_number',
        'retention_amount_ves',
        'purchase_total_usd',
        'purchase_total_ves_at_issue',
        'total_ves_at_system_registration',
        'payment_method',
        'payment_form',
        'paid_at',
        'amount_paid_ves',
        'amount_paid_usd',
        'bcv_rate_at_payment',
        'payment_bank_account_type',
        'payment_bank_code',
        'payment_bank_name',
        'payment_reference',
        'notes',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_at' => 'date',
            'registered_in_system_date' => 'date',
            'retention_voucher_issued_at' => 'date',
            'retention_voucher_number' => 'integer',
            'retention_amount_ves' => 'decimal:2',
            'purchase_total_usd' => 'decimal:2',
            'purchase_total_ves_at_issue' => 'decimal:2',
            'total_ves_at_system_registration' => 'decimal:2',
            'paid_at' => 'datetime',
            'amount_paid_ves' => 'decimal:2',
            'amount_paid_usd' => 'decimal:2',
            'bcv_rate_at_payment' => 'decimal:8',
            'payment_bank_account_type' => PaymentBankAccountType::class,
        ];
    }

    /**
     * Banco de origen del pago: nombre escrito a mano (electrónica/extranjera) o del catálogo venezolano.
     */
    public function paymentBankName(): ?string
    {
        if ($this->payment_bank_account_type?->requiresManualBankName()) {
            return filled($this->payment_bank_name) ? (string) $this->payment_bank_name : null;
        }

        if (! filled($this->payment_bank_code)) {
            return null;
        }

        return VenezuelanPagoMovilBank::tryFrom((string) $this->payment_bank_code)?->bankName()
            ?? (string) $this->payment_bank_code;
    }

    /**
     * Ej.: «Banesco · Corriente». Null en pagos registrados antes de capturar el banco.
     */
    public function paymentBankLabel(): ?string
    {
        $bankName = $this->paymentBankName();
        $accountType = $this->payment_bank_account_type?->label();

        if ($bankName === null && $accountType === null) {
            return null;
        }

        return implode(' · ', array_filter([$bankName, $accountType]));
    }

    /**
     * @return BelongsTo<Purchase, $this>
     */
    public function purchase(): BelongsTo
    {
        return $this->belongsTo(Purchase::class);
    }

    /**
     * @return BelongsTo<Branch, $this>
     */
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class);
    }

    /**
     * @return BelongsTo<AccountsPayable, $this>
     */
    public function accountsPayable(): BelongsTo
    {
        return $this->belongsTo(AccountsPayable::class);
    }
}
