<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Renombra la columna Zelle al nombre usado por el modelo/formulario y añade ruta del comprobante de contado.
     */
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        if (Schema::hasColumn('orders', 'partner_zelle_reference_email')
            && ! Schema::hasColumn('orders', 'partner_zelle_reference_name')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->renameColumn('partner_zelle_reference_email', 'partner_zelle_reference_name');
            });
        }

        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'partner_cash_payment_proof_path')) {
                $table->string('partner_cash_payment_proof_path', 512)
                    ->nullable()
                    ->after('partner_zelle_transaction_number')
                    ->comment('Ruta en disco público del comprobante de pago de contado');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            if (Schema::hasColumn('orders', 'partner_cash_payment_proof_path')) {
                $table->dropColumn('partner_cash_payment_proof_path');
            }
        });

        if (Schema::hasColumn('orders', 'partner_zelle_reference_name')
            && ! Schema::hasColumn('orders', 'partner_zelle_reference_email')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->renameColumn('partner_zelle_reference_name', 'partner_zelle_reference_email');
            });
        }
    }
};
