<?php

namespace App\Support\Products;

use App\Models\ActiveIngredient;
use App\Models\Product;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Catálogo de principios activos usado por productos (valores guardados como nombre en JSON).
 */
final class ActiveIngredientCatalog
{
    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return ActiveIngredient::query()
            ->orderBy('name')
            ->pluck('name', 'name')
            ->all();
    }

    public static function createUnique(string $name): string
    {
        $name = trim($name);
        if ($name === '') {
            throw ValidationException::withMessages([
                'name' => 'Indique el nombre del principio activo.',
            ]);
        }

        $slug = Str::slug($name);
        if ($slug === '') {
            throw ValidationException::withMessages([
                'name' => 'El nombre no genera un identificador válido.',
            ]);
        }

        $existing = ActiveIngredient::query()
            ->where('name', $name)
            ->orWhere('slug', $slug)
            ->first();

        if ($existing instanceof ActiveIngredient) {
            return $existing->name;
        }

        ActiveIngredient::query()->create([
            'name' => $name,
            'slug' => $slug,
        ]);

        return $name;
    }

    public static function rename(string $currentName, string $newName): string
    {
        $currentName = trim($currentName);
        $newName = trim($newName);

        if ($currentName === '' || $newName === '') {
            throw ValidationException::withMessages([
                'name' => 'Indique el principio y el nuevo nombre.',
            ]);
        }

        if (mb_strtolower($currentName) === mb_strtolower($newName)) {
            return $currentName;
        }

        $ingredient = ActiveIngredient::query()->where('name', $currentName)->first();
        if (! $ingredient instanceof ActiveIngredient) {
            throw ValidationException::withMessages([
                'ingredient_name' => 'No se encontró ese principio en el catálogo.',
            ]);
        }

        $slug = Str::slug($newName);
        $duplicate = ActiveIngredient::query()
            ->where('id', '!=', $ingredient->id)
            ->where(function ($query) use ($newName, $slug): void {
                $query->where('name', $newName)->orWhere('slug', $slug);
            })
            ->exists();

        if ($duplicate) {
            throw ValidationException::withMessages([
                'name' => 'Ya existe otro principio activo con ese nombre.',
            ]);
        }

        $ingredient->update([
            'name' => $newName,
            'slug' => $slug,
        ]);

        Product::query()
            ->whereJsonContains('active_ingredient', $currentName)
            ->select(['id', 'active_ingredient'])
            ->orderBy('id')
            ->each(function (Product $product) use ($currentName, $newName): void {
                $list = $product->active_ingredient;
                if (! is_array($list)) {
                    return;
                }

                $updated = false;
                foreach ($list as $index => $value) {
                    if (is_string($value) && mb_strtolower(trim($value)) === mb_strtolower($currentName)) {
                        $list[$index] = $newName;
                        $updated = true;
                    }
                }

                if ($updated) {
                    $product->update([
                        'active_ingredient' => array_values(array_unique($list)),
                    ]);
                }
            });

        return $newName;
    }
}
