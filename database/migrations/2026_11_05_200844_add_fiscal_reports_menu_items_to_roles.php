<?php

use App\Models\Rol;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Los roles que ya ven «Libro de Ventas» reciben «Reportes X» y «Reportes Z».
     */
    public function up(): void
    {
        Rol::query()->each(function (Rol $rol): void {
            if (! is_array($rol->allowed_menu_items) || ! in_array('sales_book', $rol->allowed_menu_items, true)) {
                return;
            }

            $rol->allowed_menu_items = array_values(array_unique([...$rol->allowed_menu_items, 'fiscal_x_reports', 'fiscal_z_reports']));
            $rol->save();
        });
    }

    public function down(): void
    {
        Rol::query()->each(function (Rol $rol): void {
            if (! is_array($rol->allowed_menu_items)) {
                return;
            }

            $rol->allowed_menu_items = array_values(array_diff($rol->allowed_menu_items, ['fiscal_x_reports', 'fiscal_z_reports']));
            $rol->save();
        });
    }
};
