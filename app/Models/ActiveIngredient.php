<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class ActiveIngredient extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'slug' => 'string',
        ];
    }

    /**
     * @return BelongsToMany<FormularyCategory, $this>
     */
    public function formularyCategories(): BelongsToMany
    {
        return $this->belongsToMany(FormularyCategory::class)->withPivot(['presentation', 'sort'])->withTimestamps();
    }
}
