<?php

use App\Enums\ProductType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'slug')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->string('slug')->nullable()->unique()->after('name')->comment('Slug para URLs o búsqueda amigable');
            });
        }

        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'product_category_id')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->foreignId('product_category_id')
                    ->nullable()
                    ->after('supplier_id')
                    ->constrained('product_categories')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('product_categories') && ! Schema::hasColumn('product_categories', 'is_medication')) {
            Schema::table('product_categories', function (Blueprint $table): void {
                $table->boolean('is_medication')->default(false)->after('is_active');
            });
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'product_type')) {
            $medValue = ProductType::Medication->value;
            $categoryIds = DB::table('products')
                ->where('product_type', $medValue)
                ->whereNotNull('product_category_id')
                ->distinct()
                ->pluck('product_category_id');

            foreach ($categoryIds as $cid) {
                if ($cid !== null && (int) $cid > 0) {
                    DB::table('product_categories')->where('id', (int) $cid)->update(['is_medication' => true]);
                }
            }
        }

        if (Schema::hasTable('inventories') && ! Schema::hasColumn('inventories', 'product_category_id')) {
            Schema::table('inventories', function (Blueprint $table): void {
                $table->foreignId('product_category_id')
                    ->nullable()
                    ->after('product_id')
                    ->constrained('product_categories')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('inventories') && Schema::hasColumn('inventories', 'product_category_id') && Schema::hasTable('products')) {
            DB::table('inventories')
                ->orderBy('id')
                ->select(['id', 'product_id'])
                ->chunkById(500, function ($rows): void {
                    foreach ($rows as $row) {
                        $pid = $row->product_id ?? null;
                        if ($pid === null) {
                            continue;
                        }
                        $catId = DB::table('products')->where('id', (int) $pid)->value('product_category_id');
                        DB::table('inventories')->where('id', (int) $row->id)->update([
                            'product_category_id' => $catId,
                        ]);
                    }
                });
        }

        if (Schema::hasTable('inventories') && Schema::hasColumn('inventories', 'product_type')) {
            Schema::table('inventories', function (Blueprint $table): void {
                $table->dropColumn('product_type');
            });
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'product_type')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->dropColumn('product_type');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('products') && ! Schema::hasColumn('products', 'product_type')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->string('product_type')->nullable()->index()->after('description');
            });
        }

        if (Schema::hasTable('inventories') && ! Schema::hasColumn('inventories', 'product_type')) {
            Schema::table('inventories', function (Blueprint $table): void {
                $table->string('product_type')->nullable()->after('product_id');
            });
        }

        if (Schema::hasTable('inventories') && Schema::hasColumn('inventories', 'product_category_id')) {
            Schema::table('inventories', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('product_category_id');
            });
        }

        if (Schema::hasTable('product_categories') && Schema::hasColumn('product_categories', 'is_medication')) {
            Schema::table('product_categories', function (Blueprint $table): void {
                $table->dropColumn('is_medication');
            });
        }

        if (Schema::hasTable('products') && Schema::hasColumn('products', 'product_category_id')) {
            Schema::table('products', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('product_category_id');
            });
        }
    }
};
