<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('fallas_existencia') || DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->dropProductForeignKeys();

        Schema::table('fallas_existencia', function (Blueprint $table): void {
            $table->foreign('product_id', 'fallas_existencia_product_id_foreign')
                ->references('id')
                ->on('products')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('fallas_existencia') || DB::getDriverName() !== 'mysql') {
            return;
        }

        $this->dropProductForeignKeys();

        Schema::table('fallas_existencia', function (Blueprint $table): void {
            $table->foreign('product_id', 'fallas_existencia_product_id_foreign')
                ->references('id')
                ->on('products')
                ->restrictOnDelete();
        });
    }

    private function dropProductForeignKeys(): void
    {
        $database = DB::getDatabaseName();
        $constraints = DB::select(
            'SELECT DISTINCT CONSTRAINT_NAME FROM information_schema.KEY_COLUMN_USAGE WHERE TABLE_SCHEMA = ? AND TABLE_NAME = ? AND COLUMN_NAME = ? AND REFERENCED_TABLE_NAME IS NOT NULL',
            [$database, 'fallas_existencia', 'product_id'],
        );

        Schema::disableForeignKeyConstraints();

        try {
            foreach ($constraints as $row) {
                $name = (string) $row->CONSTRAINT_NAME;
                DB::statement("ALTER TABLE `fallas_existencia` DROP FOREIGN KEY `{$name}`");
            }
        } finally {
            Schema::enableForeignKeyConstraints();
        }
    }
};
