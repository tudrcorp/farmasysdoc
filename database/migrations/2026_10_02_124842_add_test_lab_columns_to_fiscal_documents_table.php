<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('fiscal_documents', function (Blueprint $table): void {
            $table->boolean('is_test')->default(false)->after('simulation')
                ->comment('Laboratorio fiscal: prueba pedida por un administrador, sin venta asociada');
            $table->foreignId('related_document_id')->nullable()->after('sale_id')
                ->constrained('fiscal_documents')->nullOnDelete()
                ->comment('Nota de crédito de prueba → factura de prueba que anula');
            $table->index(['fiscal_printer_id', 'is_test', 'id']);
        });
    }

    public function down(): void
    {
        Schema::table('fiscal_documents', function (Blueprint $table): void {
            $table->dropIndex(['fiscal_printer_id', 'is_test', 'id']);
            $table->dropConstrainedForeignId('related_document_id');
            $table->dropColumn('is_test');
        });
    }
};
