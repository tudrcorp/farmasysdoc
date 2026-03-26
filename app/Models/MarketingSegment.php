<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingSegment extends Model
{
    protected $fillable = [
        'name',
        'description',
        'rules',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'rules' => 'array',
            'is_active' => 'boolean',
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
