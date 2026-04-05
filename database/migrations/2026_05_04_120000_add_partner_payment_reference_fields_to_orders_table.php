<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Referencias declaradas por el aliado al pagar de contado (pago móvil / Zelle).
     */
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'partner_pago_movil_reference')) {
                $table->string('partner_pago_movil_reference', 255)
                    ->nullable()
                    ->after('partner_cash_payment_method')
                    ->comment('Referencia del pago móvil');
            }
            if (! Schema::hasColumn('orders', 'partner_zelle_reference_email')) {
                $table->string('partner_zelle_reference_email', 255)
                    ->nullable()
                    ->after('partner_pago_movil_reference')
                    ->comment('Correo de referencia Zelle');
            }
            if (! Schema::hasColumn('orders', 'partner_zelle_transaction_number')) {
                $table->string('partner_zelle_transaction_number', 255)
                    ->nullable()
                    ->after('partner_zelle_reference_email')
                    ->comment('Número o referencia de transacción Zelle');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            $drops = array_values(array_filter([
                Schema::hasColumn('orders', 'partner_zelle_transaction_number') ? 'partner_zelle_transaction_number' : null,
                Schema::hasColumn('orders', 'partner_zelle_reference_email') ? 'partner_zelle_reference_email' : null,
                Schema::hasColumn('orders', 'partner_pago_movil_reference') ? 'partner_pago_movil_reference' : null,
            ]));
            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });
    }
};
