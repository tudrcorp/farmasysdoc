<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('products', 'branch_id')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (Schema::hasColumn('products', 'branch_id')) {
            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->foreignId('branch_id')
                ->nullable()
                ->after('supplier_id')
                ->constrained('branches')
                ->nullOnDelete();
        });
    }
};
