<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_books', function (Blueprint $table): void {
            $table->dropUnique(['voucher_number']);
            $table->index('voucher_number');
        });
    }

    public function down(): void
    {
        Schema::table('purchase_books', function (Blueprint $table): void {
            $table->dropIndex(['voucher_number']);
            $table->unique('voucher_number');
        });
    }
};
