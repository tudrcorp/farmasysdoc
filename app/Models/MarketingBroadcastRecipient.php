<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MarketingBroadcastRecipient extends Model
{
    protected $fillable = [
        'marketing_broadcast_id',
        'client_id',
        'email_status',
        'whatsapp_status',
        'email_sent_at',
        'whatsapp_sent_at',
        'error_message',
    ];

    protected function casts(): array
    {
        return [
            'email_sent_at' => 'datetime',
            'whatsapp_sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MarketingBroadcast, $this>
     */
    public function broadcast(): BelongsTo
    {
        return $this->belongsTo(MarketingBroadcast::class, 'marketing_broadcast_id');
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
