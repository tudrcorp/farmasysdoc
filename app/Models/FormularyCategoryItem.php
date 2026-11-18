<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Presentación cubierta por una categoría de catálogo: principio activo + concentración/forma.
 */
class FormularyCategoryItem extends Model
{
    protected $table = 'active_ingredient_formulary_category';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'formulary_category_id',
        'active_ingredient_id',
        'presentation',
        'sort',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sort' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<FormularyCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(FormularyCategory::class, 'formulary_category_id');
    }

    /**
     * @return BelongsTo<ActiveIngredient, $this>
     */
    public function activeIngredient(): BelongsTo
    {
        return $this->belongsTo(ActiveIngredient::class);
    }

    public function label(): string
    {
        $name = (string) $this->activeIngredient?->name;

        return $this->presentation === '' ? $name : "{$name} · {$this->presentation}";
    }
}
