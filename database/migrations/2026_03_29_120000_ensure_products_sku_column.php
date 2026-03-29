<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Idempotente: asegura la columna `sku` si falta (p. ej. migración previa vacía o esquema antiguo).
     */
    public function up(): void
    {
        if (Schema::hasColumn('products', 'sku')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->string('sku')
                ->nullable()
                ->after('id')
                ->comment('Código SKU interno del artículo (único)');
        });

        foreach (DB::table('products')->whereNull('sku')->orderBy('id')->cursor() as $row) {
            DB::table('products')
                ->where('id', $row->id)
                ->update(['sku' => 'SKU-LEGACY-'.$row->id]);
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->string('sku')->nullable(false)->unique()->change();
        });
    }

    public function down(): void
    {
        //
    }
};
