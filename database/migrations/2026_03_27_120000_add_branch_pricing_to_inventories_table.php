<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Precios e impuestos por sucursal (inventario); se rellenan desde el catálogo actual.
     */
    public function up(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->decimal('sale_price', 12, 2)->default(0)->after('product_id')->comment('Precio de venta en esta sucursal');
            $table->decimal('cost_price', 12, 2)->nullable()->after('sale_price')->comment('Costo unitario en esta sucursal');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('cost_price')->comment('IVA u otro impuesto (%)');
            $table->decimal('discount_percent', 5, 2)->default(0)->after('tax_rate')->comment('Descuento % sobre precio lista antes de impuesto');
        });

        $driver = Schema::getConnection()->getDriverName();
        if ($driver === 'mysql' || $driver === 'mariadb') {
            DB::statement('
                UPDATE inventories AS i
                INNER JOIN products AS p ON p.id = i.product_id
                SET
                    i.sale_price = p.sale_price,
                    i.cost_price = p.cost_price,
                    i.tax_rate = p.tax_rate
            ');
        } else {
            DB::statement('
                UPDATE inventories
                SET
                    sale_price = (SELECT sale_price FROM products WHERE products.id = inventories.product_id),
                    cost_price = (SELECT cost_price FROM products WHERE products.id = inventories.product_id),
                    tax_rate = (SELECT tax_rate FROM products WHERE products.id = inventories.product_id)
            ');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table) {
            $table->dropColumn([
                'sale_price',
                'cost_price',
                'tax_rate',
                'discount_percent',
            ]);
        });
    }
};
