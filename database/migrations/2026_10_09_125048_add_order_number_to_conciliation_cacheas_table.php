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
        Schema::table('conciliation_cacheas', function (Blueprint $table): void {
            $table->string('order_number', 64)
                ->nullable()
                ->after('sale_number')
                ->comment('Nro. de orden de Cashea indicado por el cajero');
            $table->unique('order_number');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conciliation_cacheas', function (Blueprint $table): void {
            $table->dropUnique(['order_number']);
            $table->dropColumn('order_number');
        });
    }
};
