<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * El formulario de producto trata el proveedor principal como opcional,
     * pero la columna se creó NOT NULL.
     */
    public function up(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'supplier_id')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedInteger('supplier_id')
                ->nullable()
                ->comment('Proveedor principal del producto (opcional)')
                ->change();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('products') || ! Schema::hasColumn('products', 'supplier_id')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->unsignedInteger('supplier_id')
                ->nullable(false)
                ->comment('Proveedor principal del producto (opcional)')
                ->change();
        });
    }
};
