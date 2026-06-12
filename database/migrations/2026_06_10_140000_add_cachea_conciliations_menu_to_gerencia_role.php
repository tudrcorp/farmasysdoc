<?php

use App\Models\Rol;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        foreach (['GERENCIA', 'GERENTE'] as $roleName) {
            $rol = Rol::query()->where('name', $roleName)->first();
            if (! $rol instanceof Rol || ! is_array($rol->allowed_menu_items)) {
                continue;
            }

            $items = $rol->allowed_menu_items;
            if (in_array('cachea_conciliations', $items, true)) {
                continue;
            }

            $items[] = 'cachea_conciliations';
            $rol->allowed_menu_items = $items;
            $rol->save();
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        foreach (['GERENCIA', 'GERENTE'] as $roleName) {
            $rol = Rol::query()->where('name', $roleName)->first();
            if (! $rol instanceof Rol || ! is_array($rol->allowed_menu_items)) {
                continue;
            }

            $items = array_values(array_filter(
                $rol->allowed_menu_items,
                static fn (mixed $key): bool => $key !== 'cachea_conciliations',
            ));
            $rol->allowed_menu_items = $items;
            $rol->save();
        }
    }
};
