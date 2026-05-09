<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('purchases', 'supplier_invoice_photo_path')) {
            Schema::table('purchases', function (Blueprint $table): void {
                $table->string('supplier_invoice_photo_path')
                    ->nullable()
                    ->after('supplier_control_number')
                    ->comment('Ruta privada de la foto de la factura cargada por el usuario');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('purchases', 'supplier_invoice_photo_path')) {
            Schema::table('purchases', function (Blueprint $table): void {
                $table->dropColumn('supplier_invoice_photo_path');
            });
        }
    }
};
