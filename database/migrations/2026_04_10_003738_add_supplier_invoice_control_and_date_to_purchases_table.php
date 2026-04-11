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
        Schema::table('purchases', function (Blueprint $table): void {
            if (! Schema::hasColumn('purchases', 'supplier_control_number')) {
                $table->string('supplier_control_number')->nullable()->after('supplier_invoice_number');
            }
            if (! Schema::hasColumn('purchases', 'supplier_invoice_date')) {
                $table->date('supplier_invoice_date')->nullable()->after('supplier_control_number');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table): void {
            if (Schema::hasColumn('purchases', 'supplier_invoice_date')) {
                $table->dropColumn('supplier_invoice_date');
            }
            if (Schema::hasColumn('purchases', 'supplier_control_number')) {
                $table->dropColumn('supplier_control_number');
            }
        });
    }
};
