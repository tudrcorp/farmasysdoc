<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Política unificada: precio lista, costo, IVA y descuento % viven en el producto (todas las sucursales).
     */
    public function up(): void
    {
        Schema::table('products', function (Blueprint $table): void {
            $table->decimal('sale_price', 12, 2)->default(0)->after('net_content_label')->comment('Precio de venta al público (lista)');
            $table->decimal('cost_price', 12, 2)->nullable()->after('sale_price')->comment('Costo de adquisición o valoración');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('cost_price')->comment('Porcentaje de impuesto (IVA u otro)');
            $table->decimal('discount_percent', 5, 2)->default(0)->after('tax_rate')->comment('Descuento % sobre precio lista antes de impuesto');
        });

        if (Schema::hasColumn('inventories', 'sale_price')) {
            $productIds = DB::table('products')->pluck('id');

            foreach ($productIds as $productId) {
                $row = DB::table('inventories')
                    ->where('product_id', $productId)
                    ->orderBy('id')
                    ->first();

                if ($row !== null) {
                    DB::table('products')->where('id', $productId)->update([
                        'sale_price' => (float) $row->sale_price,
                        'cost_price' => $row->cost_price !== null ? (float) $row->cost_price : null,
                        'tax_rate' => (float) $row->tax_rate,
                        'discount_percent' => (float) ($row->discount_percent ?? 0),
                    ]);
                }
            }

            Schema::table('inventories', function (Blueprint $table): void {
                $table->dropColumn([
                    'sale_price',
                    'cost_price',
                    'tax_rate',
                    'discount_percent',
                ]);
            });
        }
    }

    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table): void {
            $table->decimal('sale_price', 12, 2)->default(0)->after('product_id')->comment('Precio de venta en esta sucursal');
            $table->decimal('cost_price', 12, 2)->nullable()->after('sale_price')->comment('Costo unitario en esta sucursal');
            $table->decimal('tax_rate', 5, 2)->default(0)->after('cost_price')->comment('IVA u otro impuesto (%)');
            $table->decimal('discount_percent', 5, 2)->default(0)->after('tax_rate')->comment('Descuento % sobre precio lista antes de impuesto');
        });

        $products = DB::table('products')->select(['id', 'sale_price', 'cost_price', 'tax_rate', 'discount_percent'])->get();

        foreach ($products as $product) {
            DB::table('inventories')->where('product_id', $product->id)->update([
                'sale_price' => (float) $product->sale_price,
                'cost_price' => $product->cost_price !== null ? (float) $product->cost_price : null,
                'tax_rate' => (float) $product->tax_rate,
                'discount_percent' => (float) $product->discount_percent,
            ]);
        }

        Schema::table('products', function (Blueprint $table): void {
            $table->dropColumn([
                'sale_price',
                'cost_price',
                'tax_rate',
                'discount_percent',
            ]);
        });
    }
};
