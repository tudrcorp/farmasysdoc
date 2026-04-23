<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table): void {
            $table->unique(
                ['supplier_id', 'supplier_invoice_number'],
                'purchases_supplier_id_invoice_number_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table): void {
            $table->dropUnique('purchases_supplier_id_invoice_number_unique');
        });
    }
};
