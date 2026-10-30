<?php

use App\Models\Rol;
use Illuminate\Database\Migrations\Migration;

return new class extends Migration
{
    public function up(): void
    {
        Rol::query()->each(function (Rol $rol): void {
            if (! is_array($rol->allowed_menu_items) || mb_strtoupper((string) $rol->name) !== 'ADMINISTRADOR') {
                return;
            }

            $rol->allowed_menu_items = array_values(array_unique([...$rol->allowed_menu_items, 'ai_agent']));
            $rol->save();
        });
    }

    public function down(): void
    {
        Rol::query()->each(function (Rol $rol): void {
            if (! is_array($rol->allowed_menu_items)) {
                return;
            }

            $rol->allowed_menu_items = array_values(array_diff($rol->allowed_menu_items, ['ai_agent']));
            $rol->save();
        });
    }
};
