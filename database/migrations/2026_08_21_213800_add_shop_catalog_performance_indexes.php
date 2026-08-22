<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Índices de lectura para el catálogo de la PWA (`/app`).
     */
    public function up(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            if (Schema::hasColumn('products', 'is_active') && Schema::hasColumn('products', 'product_category_id')
                && ! Schema::hasIndex('products', 'products_shop_category_idx')) {
                $table->index(['is_active', 'product_category_id'], 'products_shop_category_idx');
            }

            if (Schema::hasColumn('products', 'is_active') && Schema::hasColumn('products', 'discount_percent')
                && ! Schema::hasIndex('products', 'products_shop_discount_idx')) {
                $table->index(['is_active', 'discount_percent'], 'products_shop_discount_idx');
            }

            if (Schema::hasColumn('products', 'is_active') && Schema::hasColumn('products', 'name')
                && ! Schema::hasIndex('products', 'products_shop_name_idx')) {
                $table->index(['is_active', 'name'], 'products_shop_name_idx');
            }

            if (Schema::hasColumn('products', 'brand') && ! Schema::hasIndex('products', 'products_shop_brand_idx')) {
                $table->index('brand', 'products_shop_brand_idx');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('products')) {
            return;
        }

        Schema::table('products', function (Blueprint $table): void {
            foreach ([
                'products_shop_category_idx',
                'products_shop_discount_idx',
                'products_shop_name_idx',
                'products_shop_brand_idx',
            ] as $index) {
                if (Schema::hasIndex('products', $index)) {
                    $table->dropIndex($index);
                }
            }
        });
    }
};
