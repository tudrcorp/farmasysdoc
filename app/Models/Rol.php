<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Rol extends Model
{
    //
    protected $table = 'rols';

    protected $casts = [
        'is_active' => 'boolean',
    ];

    protected $fillable = ['name', 'description', 'is_active'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
