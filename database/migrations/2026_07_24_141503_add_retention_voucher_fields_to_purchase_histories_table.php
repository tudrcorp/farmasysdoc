<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchase_histories', function (Blueprint $table) {
            $table->date('retention_voucher_issued_at')
                ->nullable()
                ->after('supplier_name')
                ->comment('Fecha de emisión del comprobante = día en que se imprime desde Libro de Compras');
            $table->unsignedBigInteger('retention_voucher_number')
                ->nullable()
                ->after('retention_voucher_issued_at')
                ->comment('Número de comprobante de retención IVA');
            $table->decimal('retention_amount_ves', 18, 2)
                ->nullable()
                ->after('retention_voucher_number')
                ->comment('Monto retenido IVA en Bs');
        });

        if (Schema::hasTable('purchase_books')) {
            DB::table('purchase_histories')
                ->join('purchase_books', 'purchase_books.purchase_id', '=', 'purchase_histories.purchase_id')
                ->update([
                    'purchase_histories.retention_voucher_number' => DB::raw('purchase_books.voucher_number'),
                    'purchase_histories.retention_amount_ves' => DB::raw('purchase_books.tax_retained_ves'),
                    'purchase_histories.retention_voucher_issued_at' => DB::raw('purchase_books.issue_date'),
                ]);
        }
    }

    public function down(): void
    {
        Schema::table('purchase_histories', function (Blueprint $table) {
            $table->dropColumn([
                'retention_voucher_issued_at',
                'retention_voucher_number',
                'retention_amount_ves',
            ]);
        });
    }
};
