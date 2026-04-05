<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unifica estados: ya no se usa «en proceso»; pasa a «pendiente».
     */
    public function up(): void
    {
        if (! Schema::hasTable('product_transfers')) {
            return;
        }

        DB::table('product_transfers')
            ->whereIn('status', ['in_progress', 'en_proceso', 'En proceso', 'en proceso'])
            ->update(['status' => 'pending']);
    }

    /**
     * No se revierte el cambio de significado de estado.
     */
    public function down(): void
    {
        //
    }
};
