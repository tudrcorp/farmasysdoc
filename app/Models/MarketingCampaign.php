<?php

namespace App\Models;

use App\Enums\MarketingCampaignStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingCampaign extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'description',
        'status',
        'starts_at',
        'ends_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'status' => MarketingCampaignStatus::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<MarketingBroadcast, $this>
     */
    public function broadcasts(): HasMany
    {
        return $this->hasMany(MarketingBroadcast::class);
    }
}
