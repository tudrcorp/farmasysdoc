<?php

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
        if (! Schema::hasTable('orders') || Schema::hasColumn('orders', 'is_wholesale')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->boolean('is_wholesale')
                ->default(false)
                ->comment('true = pedido al mayor (cantidades en cajas); false = al detalle (unidades)');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'is_wholesale')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('is_wholesale');
        });
    }
};
