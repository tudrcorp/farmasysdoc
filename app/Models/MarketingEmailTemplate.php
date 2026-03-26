<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class MarketingEmailTemplate extends Model
{
    protected $fillable = [
        'name',
        'subject',
        'body_html',
        'body_plain',
        'variables',
        'is_active',
    ];

    protected function casts(): array
    {
        return [
            'variables' => 'array',
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
