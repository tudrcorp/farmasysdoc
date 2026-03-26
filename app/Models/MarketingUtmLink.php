<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MarketingUtmLink extends Model
{
    protected $fillable = [
        'name',
        'base_url',
        'utm_source',
        'utm_medium',
        'utm_campaign',
        'utm_content',
        'utm_term',
        'full_url',
        'clicks_count',
    ];

    protected static function booted(): void
    {
        static::saving(function (MarketingUtmLink $link): void {
            $link->full_url = $link->buildFullUrl();
        });
    }

    public function buildFullUrl(): string
    {
        if (blank($this->base_url)) {
            return '';
        }

        $base = rtrim((string) $this->base_url, '?&');
        $params = array_filter([
            'utm_source' => $this->utm_source,
            'utm_medium' => $this->utm_medium,
            'utm_campaign' => $this->utm_campaign,
            'utm_content' => $this->utm_content,
            'utm_term' => $this->utm_term,
        ], fn ($v) => filled($v));

        if ($params === []) {
            return $base;
        }

        $sep = str_contains($base, '?') ? '&' : '?';

        return $base.$sep.http_build_query($params);
    }
}
