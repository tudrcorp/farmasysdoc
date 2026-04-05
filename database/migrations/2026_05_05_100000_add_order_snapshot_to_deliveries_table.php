<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Snapshot del pedido al generar la entrega (aliado + delivery), útil en operaciones sin abrir la orden.
     */
    public function up(): void
    {
        if (! Schema::hasTable('deliveries')) {
            return;
        }

        Schema::table('deliveries', function (Blueprint $table): void {
            if (! Schema::hasColumn('deliveries', 'order_snapshot')) {
                $table->json('order_snapshot')->nullable()->after('taken_by');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('deliveries')) {
            return;
        }

        Schema::table('deliveries', function (Blueprint $table): void {
            if (Schema::hasColumn('deliveries', 'order_snapshot')) {
                $table->dropColumn('order_snapshot');
            }
        });
    }
};
