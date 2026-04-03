<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Elimina el porcentaje de impuesto por línea/producto; el sistema ya no persiste IVA a nivel de catálogo ni de líneas de detalle.
     */
    public function up(): void
    {
        $tables = ['products', 'sale_items', 'order_items', 'purchase_items'];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'tax_rate')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->dropColumn('tax_rate');
                });
            }
        }

        if (Schema::hasTable('order_service_items') && Schema::hasColumn('order_service_items', 'tax_rate')) {
            Schema::table('order_service_items', function (Blueprint $blueprint): void {
                $blueprint->dropColumn('tax_rate');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'tax_rate')) {
            Schema::table('products', function (Blueprint $blueprint): void {
                $blueprint->decimal('tax_rate', 5, 2)->default(0)->after('cost_price');
            });
        }

        if (Schema::hasTable('sale_items') && ! Schema::hasColumn('sale_items', 'tax_rate')) {
            Schema::table('sale_items', function (Blueprint $blueprint): void {
                $blueprint->decimal('tax_rate', 5, 2)->default(0)->after('discount_amount');
            });
        }

        if (Schema::hasTable('order_items') && ! Schema::hasColumn('order_items', 'tax_rate')) {
            Schema::table('order_items', function (Blueprint $blueprint): void {
                $blueprint->decimal('tax_rate', 5, 2)->default(0)->after('discount_amount');
            });
        }

        if (Schema::hasTable('purchase_items') && ! Schema::hasColumn('purchase_items', 'tax_rate')) {
            Schema::table('purchase_items', function (Blueprint $blueprint): void {
                $blueprint->decimal('tax_rate', 5, 2)->default(0)->after('unit_cost');
            });
        }

        if (Schema::hasTable('order_service_items') && ! Schema::hasColumn('order_service_items', 'tax_rate')) {
            Schema::table('order_service_items', function (Blueprint $blueprint): void {
                $blueprint->decimal('tax_rate', 5, 2)->default(0);
            });
        }
    }
};
