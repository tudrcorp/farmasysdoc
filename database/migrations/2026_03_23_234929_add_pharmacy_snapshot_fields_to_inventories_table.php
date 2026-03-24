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
        Schema::table('inventories', function (Blueprint $table) {
            $table->string('product_type')
                ->nullable()
                ->after('notes')
                ->index()
                ->comment('Tipo de producto (snapshot en sucursal; alineado con catálogo)');
            $table->text('active_ingredient')
                ->nullable()
                ->after('product_type')
                ->comment('Principio(s) activo(s) del artículo en este inventario');
            $table->string('concentration')
                ->nullable()
                ->after('active_ingredient')
                ->comment('Concentración del principio activo');
            $table->string('presentation_type')
                ->nullable()
                ->after('concentration')
                ->comment('Forma farmacéutica: tableta, jarabe, crema, etc.');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->dropColumn([
                'product_type',
                'active_ingredient',
                'concentration',
                'presentation_type',
            ]);
        });
    }
};
