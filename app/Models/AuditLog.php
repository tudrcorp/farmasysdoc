<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AuditLog extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'uid',
        'user_id',
        'user_email',
        'roles_snapshot',
        'event',
        'auditable_type',
        'auditable_id',
        'auditable_label',
        'description',
        'properties',
        'ip_address',
        'user_agent',
        'http_method',
        'url',
        'route_name',
        'panel_id',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'roles_snapshot' => 'array',
            'properties' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
