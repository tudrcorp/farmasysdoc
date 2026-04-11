<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Líneas de factura: orden de impresión/validación, integridad referencial (MySQL) y tipos alineados con `id()` de tablas padre.
     */
    public function up(): void
    {
        if (! Schema::hasTable('purchase_items')) {
            return;
        }

        Schema::table('purchase_items', function (Blueprint $table): void {
            if (! Schema::hasColumn('purchase_items', 'line_number')) {
                $table->unsignedSmallInteger('line_number')
                    ->nullable()
                    ->after('purchase_id')
                    ->comment('Orden de la línea en el documento (informes, impresión)');
            }
        });

        $this->backfillLineNumbers();

        DB::table('purchase_items')->whereNull('line_number')->update(['line_number' => 1]);

        if (DB::getDriverName() === 'mysql' && Schema::hasColumn('purchase_items', 'line_number')) {
            DB::statement('ALTER TABLE purchase_items MODIFY line_number SMALLINT UNSIGNED NOT NULL DEFAULT 1');
        }

        try {
            Schema::table('purchase_items', function (Blueprint $table): void {
                $table->unique(['purchase_id', 'line_number'], 'purchase_items_purchase_id_line_number_unique');
            });
        } catch (Throwable) {
            // Índice ya presente (re-ejecución parcial).
        }

        if (DB::getDriverName() !== 'mysql') {
            return;
        }

        Schema::disableForeignKeyConstraints();

        try {
            $this->dropMysqlForeignKeyIfExists('purchase_items', 'purchase_items_purchase_id_foreign');
            $this->dropMysqlForeignKeyIfExists('purchase_items', 'purchase_items_product_id_foreign');
            $this->dropMysqlForeignKeyIfExists('purchase_items', 'purchase_items_inventory_id_foreign');

            DB::statement('ALTER TABLE purchase_items MODIFY purchase_id BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE purchase_items MODIFY product_id BIGINT UNSIGNED NOT NULL');
            DB::statement('ALTER TABLE purchase_items MODIFY inventory_id BIGINT UNSIGNED NULL');

            Schema::table('purchase_items', function (Blueprint $table): void {
                $table->foreign('purchase_id')
                    ->references('id')
                    ->on('purchases')
                    ->cascadeOnDelete();
                $table->foreign('product_id')
                    ->references('id')
                    ->on('products')
                    ->restrictOnDelete();
            });

            if (Schema::hasTable('inventories')) {
                Schema::table('purchase_items', function (Blueprint $table): void {
                    $table->foreign('inventory_id')
                        ->references('id')
                        ->on('inventories')
                        ->nullOnDelete();
                });
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('purchase_items')) {
            return;
        }

        if (DB::getDriverName() === 'mysql') {
            Schema::disableForeignKeyConstraints();
            try {
                $this->dropMysqlForeignKeyIfExists('purchase_items', 'purchase_items_purchase_id_foreign');
                $this->dropMysqlForeignKeyIfExists('purchase_items', 'purchase_items_product_id_foreign');
                $this->dropMysqlForeignKeyIfExists('purchase_items', 'purchase_items_inventory_id_foreign');
            } finally {
                Schema::enableForeignKeyConstraints();
            }
        }

        try {
            Schema::table('purchase_items', function (Blueprint $table): void {
                $table->dropUnique('purchase_items_purchase_id_line_number_unique');
            });
        } catch (Throwable) {
            //
        }

        if (Schema::hasColumn('purchase_items', 'line_number')) {
            Schema::table('purchase_items', function (Blueprint $table): void {
                $table->dropColumn('line_number');
            });
        }
    }

    private function backfillLineNumbers(): void
    {
        $rows = DB::table('purchase_items')
            ->orderBy('purchase_id')
            ->orderBy('id')
            ->get(['id', 'purchase_id']);

        $lineByPurchase = [];

        foreach ($rows as $row) {
            $pid = (int) $row->purchase_id;
            if (! isset($lineByPurchase[$pid])) {
                $lineByPurchase[$pid] = 1;
            }
            DB::table('purchase_items')
                ->where('id', $row->id)
                ->update(['line_number' => $lineByPurchase[$pid]]);
            $lineByPurchase[$pid]++;
        }
    }

    private function dropMysqlForeignKeyIfExists(string $table, string $constraintName): void
    {
        $database = DB::getDatabaseName();
        $exists = DB::selectOne(
            'SELECT CONSTRAINT_NAME FROM information_schema.TABLE_CONSTRAINTS WHERE CONSTRAINT_SCHEMA = ? AND TABLE_NAME = ? AND CONSTRAINT_NAME = ? AND CONSTRAINT_TYPE = ?',
            [$database, $table, $constraintName, 'FOREIGN KEY'],
        );
        if ($exists !== null) {
            DB::statement("ALTER TABLE `{$table}` DROP FOREIGN KEY `{$constraintName}`");
        }
    }
};
