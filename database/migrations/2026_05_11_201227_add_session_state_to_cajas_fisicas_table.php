<?php

use App\Models\Rol;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('cajas_fisicas', function (Blueprint $table): void {
            $table->boolean('is_open')->default(false)->after('amount_ves')->comment('Si la caja tiene sesión de turno abierta');
            $table->timestamp('opened_at')->nullable()->after('is_open')->comment('Última apertura de turno');
            $table->timestamp('closed_at')->nullable()->after('opened_at')->comment('Último cierre de turno');
        });

        $rol = Rol::query()->where('name', 'CAJERO')->first();
        if ($rol instanceof Rol && is_array($rol->allowed_menu_items)) {
            $items = $rol->allowed_menu_items;
            if (! in_array('physical_cash_box', $items, true)) {
                $items[] = 'physical_cash_box';
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
                static fn (mixed $k): bool => $k !== 'physical_cash_box',
            ));
            $rol->allowed_menu_items = $items;
            $rol->save();
        }

        Schema::table('cajas_fisicas', function (Blueprint $table): void {
            $table->dropColumn(['is_open', 'opened_at', 'closed_at']);
        });
    }
};
