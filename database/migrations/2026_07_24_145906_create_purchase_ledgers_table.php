<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('purchase_ledgers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('purchase_id')->constrained('purchases')->restrictOnDelete();
            $table->foreignId('purchase_book_id')->nullable()->constrained('purchase_books')->nullOnDelete();
            $table->string('tax_period', 7)->index()->comment('yyyy/mm');
            $table->unsignedInteger('operation_number')->comment('Correlativo del periodo');
            $table->string('document_type', 40)->index()->comment('FACTURA | COMPROBANTE_DE_RETENCION');
            $table->string('document_number', 128)->comment('Nº factura o Nº comprobante retención');
            $table->string('control_number', 128)->nullable();
            $table->string('supplier_name', 255);
            $table->string('supplier_tax_id', 32)->nullable();
            $table->string('taxpayer_type', 64)->nullable();
            $table->decimal('total_with_vat_and_exempt_ves', 18, 2)->default(0);
            $table->decimal('exempt_amount_ves', 18, 2)->nullable();
            $table->decimal('export_amount_ves', 18, 2)->nullable();
            $table->decimal('taxable_base_ves', 18, 2)->nullable();
            $table->decimal('tax_caused_ves', 18, 2)->nullable();
            $table->decimal('taxable_base_reduced_ves', 18, 2)->nullable();
            $table->decimal('tax_reduced_ves', 18, 2)->nullable();
            $table->decimal('vat_rate_percent', 8, 2)->nullable();
            $table->date('retention_voucher_issued_at')->nullable();
            $table->unsignedBigInteger('retention_voucher_number')->nullable();
            $table->decimal('retention_amount_ves', 18, 2)->nullable();
            $table->date('invoice_date')->nullable()->index();
            $table->string('created_by', 191)->nullable();
            $table->timestamps();

            $table->unique(['purchase_id', 'document_type']);
            $table->unique(['tax_period', 'operation_number']);
            $table->index(['tax_period', 'document_type']);
        });

        if (Schema::hasTable('rols') && Schema::hasColumn('rols', 'allowed_menu_items')) {
            $roles = DB::table('rols')->select(['id', 'allowed_menu_items'])->get();

            foreach ($roles as $role) {
                if ($role->allowed_menu_items === null) {
                    continue;
                }

                $items = json_decode((string) $role->allowed_menu_items, true);
                if (! is_array($items)) {
                    continue;
                }

                if (! in_array('purchase_books', $items, true) && ! in_array('purchases', $items, true)) {
                    continue;
                }

                if (in_array('purchase_ledgers', $items, true)) {
                    continue;
                }

                $items[] = 'purchase_ledgers';
                DB::table('rols')->where('id', $role->id)->update([
                    'allowed_menu_items' => json_encode(array_values($items)),
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('purchase_ledgers');
    }
};
