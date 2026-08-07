<?php

namespace App\Models;

use Database\Factories\ClientDiscountGroupFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ClientDiscountGroup extends Model
{
    /** @use HasFactory<ClientDiscountGroupFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'discount_percent',
        'is_active',
        'notes',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_percent' => 'decimal:2',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<Client, $this>
     */
    public function clients(): BelongsToMany
    {
        return $this->belongsToMany(Client::class, 'client_discount_group_client')
            ->withTimestamps();
    }
}
