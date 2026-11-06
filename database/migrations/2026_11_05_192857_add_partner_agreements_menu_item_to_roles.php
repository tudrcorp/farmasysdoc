<?php

use App\Models\Rol;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    /**
     * Los roles que ya ven «Compañías aliadas» reciben «Convenios y renovaciones».
     */
    public function up(): void
    {
        Rol::query()->each(function (Rol $rol): void {
            if (! is_array($rol->allowed_menu_items) || ! in_array('partner_companies', $rol->allowed_menu_items, true)) {
                return;
            }

            $rol->allowed_menu_items = array_values(array_unique([...$rol->allowed_menu_items, 'partner_agreements']));
            $rol->save();
        });
    }

    public function down(): void
    {
        Rol::query()->each(function (Rol $rol): void {
            if (! is_array($rol->allowed_menu_items)) {
                return;
            }

            $rol->allowed_menu_items = array_values(array_diff($rol->allowed_menu_items, ['partner_agreements']));
            $rol->save();
        });
    }
};
