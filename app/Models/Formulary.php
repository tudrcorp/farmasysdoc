<?php

namespace App\Models;

use Database\Factories\FormularyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

/**
 * Catálogo corporativo de principios activos agrupados por categorías; lo pueden usar uno o varios planes.
 */
class Formulary extends Model
{
    /** @use HasFactory<FormularyFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'code',
        'name',
        'description',
        'is_active',
        'requires_prescription',
        'requires_authorization',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'requires_prescription' => 'boolean',
            'requires_authorization' => 'boolean',
        ];
    }

    /**
     * @return BelongsToMany<FormularyCategory, $this>
     */
    public function categories(): BelongsToMany
    {
        return $this->belongsToMany(FormularyCategory::class)->withTimestamps();
    }

    /**
     * @return BelongsToMany<Plan, $this>
     */
    public function plans(): BelongsToMany
    {
        return $this->belongsToMany(Plan::class)->withTimestamps();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function updater(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Principios activos distintos que cubre el catálogo a través de sus categorías.
     */
    public function coveredActiveIngredientsCount(): int
    {
        return ActiveIngredient::query()
            ->whereHas('formularyCategories.formularies', fn ($query) => $query->whereKey($this->getKey()))
            ->count();
    }

    /**
     * Requisitos de dispensación en texto (récipe / autorización).
     */
    public function requirementsLabel(): ?string
    {
        return match (true) {
            $this->requires_prescription && $this->requires_authorization => 'Récipe y autorización requeridas',
            $this->requires_prescription => 'Récipe requerido',
            $this->requires_authorization => 'Autorización requerida',
            default => null,
        };
    }

    public static function nextCode(): string
    {
        return 'CAT-'.str_pad((string) ((int) static::query()->max('id') + 1), 4, '0', STR_PAD_LEFT);
    }
}
