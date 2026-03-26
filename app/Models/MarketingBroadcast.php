<?php

namespace App\Models;

use App\Enums\MarketingBroadcastSendMode;
use App\Enums\MarketingBroadcastStatus;
use App\Enums\MarketingBroadcastType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingBroadcast extends Model
{
    protected $fillable = [
        'name',
        'type',
        'send_mode',
        'marketing_campaign_id',
        'marketing_email_template_id',
        'marketing_segment_id',
        'channels',
        'subject',
        'email_html',
        'whatsapp_body',
        'status',
        'scheduled_at',
        'started_at',
        'completed_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => MarketingBroadcastType::class,
            'send_mode' => MarketingBroadcastSendMode::class,
            'channels' => 'array',
            'status' => MarketingBroadcastStatus::class,
            'scheduled_at' => 'datetime',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<MarketingCampaign, $this>
     */
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(MarketingCampaign::class, 'marketing_campaign_id');
    }

    /**
     * @return BelongsTo<MarketingEmailTemplate, $this>
     */
    public function emailTemplate(): BelongsTo
    {
        return $this->belongsTo(MarketingEmailTemplate::class, 'marketing_email_template_id');
    }

    /**
     * @return BelongsTo<MarketingSegment, $this>
     */
    public function segment(): BelongsTo
    {
        return $this->belongsTo(MarketingSegment::class, 'marketing_segment_id');
    }

    /**
     * @return BelongsToMany<Client, $this>
     */
    public function selectedClients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'marketing_broadcast_clients')
            ->withTimestamps();
    }

    /**
     * @return HasMany<MarketingBroadcastRecipient, $this>
     */
    public function recipients(): HasMany
    {
        return $this->hasMany(MarketingBroadcastRecipient::class);
    }

    public function sendsEmail(): bool
    {
        $ch = $this->channels ?? [];

        return in_array('email', $ch, true);
    }

    public function sendsWhatsapp(): bool
    {
        $ch = $this->channels ?? [];

        return in_array('whatsapp', $ch, true);
    }
}
