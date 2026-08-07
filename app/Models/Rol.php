<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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

    /**
     * Sucursales de alcance asociadas a este rol (pivote `branch_rol`).
     *
     * @return BelongsToMany<Branch, $this>
     */
    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class, 'branch_rol')->withTimestamps();
    }

    /**
     * @return list<int>
     */
    public function branchIds(): array
    {
        $branches = $this->relationLoaded('branches')
            ? $this->branches
            : $this->branches()->get();

        return $branches->pluck('id')
            ->map(static fn (mixed $id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<int>
     */
    public static function extractBranchIdsFromData(array $data): array
    {
        $raw = $data['branch_ids'] ?? null;
        if (! is_array($raw)) {
            return [];
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (mixed $id): int => (int) $id,
            $raw,
        ), static fn (int $id): bool => $id > 0)));
    }

    /**
     * Quita del payload el campo virtual de sucursales (no es columna de `rols`).
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function stripBranchIdsFromData(array $data): array
    {
        unset($data['branch_ids']);

        return $data;
    }
}
