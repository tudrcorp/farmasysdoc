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
        $rol = Rol::query()->where('name', 'CAJERO')->first();
        if ($rol instanceof Rol && is_array($rol->allowed_menu_items)) {
            $items = $rol->allowed_menu_items;
            if (! in_array('physical_cash_box_movements', $items, true)) {
                $items[] = 'physical_cash_box_movements';
                $rol->allowed_menu_items = $items;
                $rol->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        $rol = Rol::query()->where('name', 'CAJERO')->first();
        if ($rol instanceof Rol && is_array($rol->allowed_menu_items)) {
            $items = array_values(array_filter(
                $rol->allowed_menu_items,
                static fn (mixed $k): bool => $k !== 'physical_cash_box_movements',
            ));
            $rol->allowed_menu_items = $items;
            $rol->save();
        }
    }
};
