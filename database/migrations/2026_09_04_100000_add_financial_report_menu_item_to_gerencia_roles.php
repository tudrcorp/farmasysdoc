<?php

use App\Models\Rol;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        foreach (['GERENCIA', 'GERENTE'] as $roleName) {
            $rol = Rol::query()->where('name', $roleName)->first();
            if (! $rol instanceof Rol || ! is_array($rol->allowed_menu_items)) {
                continue;
            }

            $items = $rol->allowed_menu_items;
            if (in_array('financial_report', $items, true)) {
                continue;
            }

            $items[] = 'financial_report';
            $rol->allowed_menu_items = $items;
            $rol->save();
        }
    }

    public function down(): void
    {
        foreach (['GERENCIA', 'GERENTE'] as $roleName) {
            $rol = Rol::query()->where('name', $roleName)->first();
            if (! $rol instanceof Rol || ! is_array($rol->allowed_menu_items)) {
                continue;
            }

            $rol->allowed_menu_items = array_values(array_filter(
                $rol->allowed_menu_items,
                static fn (mixed $key): bool => $key !== 'financial_report',
            ));
            $rol->save();
        }
    }
};
