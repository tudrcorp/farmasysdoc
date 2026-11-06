<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Registro de aviso de vencimiento enviado (uno por cuenta por pagar, umbral y fecha de vencimiento).
 */
class AccountsPayableDueNotice extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'accounts_payable_id',
        'threshold_days',
        'due_at',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'threshold_days' => 'integer',
            'due_at' => 'date',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<AccountsPayable, $this>
     */
    public function accountsPayable(): BelongsTo
    {
        return $this->belongsTo(AccountsPayable::class);
    }
}
