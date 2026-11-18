<?php

namespace App\Models;

use Database\Factories\FormularyCategoryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Maestro común de categorías de catálogo; cada una agrupa N presentaciones de principios activos.
 * No guarda relación con las categorías de productos.
 */
class FormularyCategory extends Model
{
    /** @use HasFactory<FormularyCategoryFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<ActiveIngredient, $this>
     */
    public function activeIngredients(): BelongsToMany
    {
        return $this->belongsToMany(ActiveIngredient::class)->withPivot(['presentation', 'sort'])->withTimestamps();
    }

    /**
     * Presentaciones (principio activo + concentración/forma) de la categoría.
     *
     * @return HasMany<FormularyCategoryItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(FormularyCategoryItem::class)->orderBy('sort')->orderBy('id');
    }

    /**
     * @return BelongsToMany<Formulary, $this>
     */
    public function formularies(): BelongsToMany
    {
        return $this->belongsToMany(Formulary::class)->withTimestamps();
    }
}
