<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Unifica estados de pedido a tres valores: pendiente, en-proceso, finalizado.
     */
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        $toEnProceso = [
            'confirmed',
            'preparing',
            'listo-para-despacho',
            'despachado',
            'en-transito',
        ];

        $toFinalizado = [
            'entregado',
            'cancelado',
        ];

        DB::table('orders')->whereIn('status', $toEnProceso)->update(['status' => 'en-proceso']);
        DB::table('orders')->whereIn('status', $toFinalizado)->update(['status' => 'finalizado']);
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        // Reversión aproximada (pérdida de granularidad previa).
        DB::table('orders')->where('status', 'en-proceso')->update(['status' => 'confirmed']);
        DB::table('orders')->where('status', 'finalizado')->update(['status' => 'entregado']);
    }
};
