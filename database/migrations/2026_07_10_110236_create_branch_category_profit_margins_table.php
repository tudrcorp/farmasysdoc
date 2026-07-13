<?php

use App\Models\Inventory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Márgenes de ganancia por sucursal y categoría de producto.
     */
    public function up(): void
    {
        Schema::create('branch_category_profit_margins', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')
                ->constrained('branches')
                ->cascadeOnUpdate()
                ->restrictOnDelete()
                ->comment('Sucursal');
            $table->foreignId('product_category_id')
                ->constrained('product_categories')
                ->cascadeOnUpdate()
                ->restrictOnDelete()
                ->comment('Categoría de producto');
            $table->decimal('profit_percentage', 12, 4)
                ->default(0)
                ->comment('Porcentaje de ganancia sobre costo para esta sucursal y categoría');
            $table->string('created_by')->nullable();
            $table->string('updated_by')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'product_category_id'], 'branch_category_profit_margins_unique');
        });

        $this->backfillFromCategoriesAndExpressStructures();
        $this->recalculateInventoryFinancialSnapshots();
    }

    public function down(): void
    {
        Schema::dropIfExists('branch_category_profit_margins');
    }

    private function backfillFromCategoriesAndExpressStructures(): void
    {
        if (! Schema::hasTable('branches') || ! Schema::hasTable('product_categories')) {
            return;
        }

        $branches = DB::table('branches')->orderBy('id')->pluck('id');
        $categories = DB::table('product_categories')->orderBy('id')->get(['id', 'profit_percentage']);

        if ($branches->isEmpty() || $categories->isEmpty()) {
            return;
        }

        $now = now();
        $rows = [];

        foreach ($branches as $branchId) {
            foreach ($categories as $category) {
                $rows[] = [
                    'branch_id' => (int) $branchId,
                    'product_category_id' => (int) $category->id,
                    'profit_percentage' => max(0.0, (float) ($category->profit_percentage ?? 0)),
                    'created_by' => 'migration',
                    'updated_by' => 'migration',
                    'created_at' => $now,
                    'updated_at' => $now,
                ];
            }
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('branch_category_profit_margins')->insert($chunk);
        }

        if (! Schema::hasTable('farma_express_cost_structures')) {
            return;
        }

        $expressStructures = DB::table('farma_express_cost_structures')
            ->select(['branch_id', 'profit_percentage'])
            ->get();

        foreach ($expressStructures as $structure) {
            DB::table('branch_category_profit_margins')
                ->where('branch_id', (int) $structure->branch_id)
                ->update([
                    'profit_percentage' => max(0.0, (float) ($structure->profit_percentage ?? 0)),
                    'updated_by' => 'migration:express',
                    'updated_at' => $now,
                ]);
        }
    }

    private function recalculateInventoryFinancialSnapshots(): void
    {
        if (! Schema::hasTable('inventories')) {
            return;
        }

        Inventory::query()
            ->orderBy('id')
            ->chunkById(200, function ($inventories): void {
                foreach ($inventories as $inventory) {
                    if (! $inventory instanceof Inventory) {
                        continue;
                    }

                    $inventory->syncFinancialSnapshotFromRelatedProductAndCost();

                    if ($inventory->isDirty()) {
                        $inventory->saveQuietly();
                    }
                }
            });
    }
};
