<?php

namespace App\Support\Plans;

use App\Models\ActiveIngredient;
use App\Models\Formulary;
use App\Models\FormularyCategoryItem;
use Illuminate\Database\Eloquent\Builder;

/**
 * Resume la cobertura (categorías, presentaciones y principios activos distintos)
 * de un conjunto de categorías o de catálogos.
 */
final class FormularyCoverage
{
    /**
     * @param  array<int, int|string>  $categoryIds
     */
    public static function forCategories(array $categoryIds): string
    {
        $ids = self::ids($categoryIds);

        if ($ids === []) {
            return 'Sin categorías seleccionadas.';
        }

        return self::describe(count($ids).' categoría(s)', $ids);
    }

    /**
     * @param  array<int, int|string>  $formularyIds
     */
    public static function forFormularies(array $formularyIds): string
    {
        $ids = self::ids($formularyIds);

        if ($ids === []) {
            return 'Sin catálogos asociados.';
        }

        $categoryIds = Formulary::query()
            ->whereKey($ids)
            ->with('categories:id')
            ->get()
            ->flatMap(fn (Formulary $formulary) => $formulary->categories->modelKeys())
            ->unique()
            ->values()
            ->all();

        return self::describe(count($ids).' catálogo(s) · '.count($categoryIds).' categoría(s)', $categoryIds);
    }

    /**
     * @param  list<int>  $categoryIds
     */
    private static function describe(string $prefix, array $categoryIds): string
    {
        $presentations = FormularyCategoryItem::query()
            ->whereIn('formulary_category_id', $categoryIds)
            ->count();

        $ingredients = ActiveIngredient::query()
            ->whereHas('formularyCategories', fn (Builder $query): Builder => $query->whereKey($categoryIds))
            ->count();

        return "{$prefix} · {$presentations} presentación(es) · {$ingredients} principio(s) activo(s) distintos";
    }

    /**
     * @param  array<int, int|string>  $ids
     * @return list<int>
     */
    private static function ids(array $ids): array
    {
        return array_values(array_unique(array_filter(array_map('intval', $ids))));
    }
}
