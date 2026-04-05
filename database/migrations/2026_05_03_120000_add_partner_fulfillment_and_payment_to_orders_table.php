<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Entrega / retiro y forma de pago para pedidos de compañías aliadas.
     */
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'partner_fulfillment_type')) {
                $table->string('partner_fulfillment_type', 32)
                    ->nullable()
                    ->after('is_wholesale')
                    ->comment('delivery | pickup — solo pedidos aliado');
            }
            if (! Schema::hasColumn('orders', 'partner_payment_terms')) {
                $table->string('partner_payment_terms', 32)
                    ->nullable()
                    ->after('partner_fulfillment_type')
                    ->comment('contado | credito');
            }
            if (! Schema::hasColumn('orders', 'partner_cash_payment_method')) {
                $table->string('partner_cash_payment_method', 32)
                    ->nullable()
                    ->after('partner_payment_terms')
                    ->comment('pago_movil | zelle | transferencia si contado');
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
                Schema::hasColumn('orders', 'partner_cash_payment_method') ? 'partner_cash_payment_method' : null,
                Schema::hasColumn('orders', 'partner_payment_terms') ? 'partner_payment_terms' : null,
                Schema::hasColumn('orders', 'partner_fulfillment_type') ? 'partner_fulfillment_type' : null,
            ]));
            if ($drops !== []) {
                $table->dropColumn($drops);
            }
        });
    }
};
