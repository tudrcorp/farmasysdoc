<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingContent extends Model
{
    protected $table = 'marketing_contents';

    protected $fillable = [
        'title',
        'slug',
        'summary',
        'body',
        'promo_type',
        'cta_label',
        'cta_url',
        'image_path',
        'is_published',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }
}
