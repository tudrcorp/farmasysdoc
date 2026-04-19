<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rol extends Model
{
    protected $table = 'rols';

    protected $casts = [
        'is_active' => 'boolean',
        'allowed_menu_items' => 'array',
    ];

    protected $fillable = ['name', 'description', 'is_active', 'allowed_menu_items'];

    protected function setNameAttribute(mixed $value): void
    {
        $this->attributes['name'] = mb_strtoupper(trim((string) $value));
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
