<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_audits', function (Blueprint $table): void {
            $table->foreignId('product_category_id')
                ->nullable()
                ->after('branch_id')
                ->constrained('product_categories')
                ->nullOnDelete();
            $table->char('letter_from', 1)->nullable()->after('product_category_id');
            $table->char('letter_to', 1)->nullable()->after('letter_from');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_audits', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('product_category_id');
            $table->dropColumn(['letter_from', 'letter_to']);
        });
    }
};
