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
        if (! Schema::hasTable('product_transfers')) {
            return;
        }

        Schema::table('product_transfers', function (Blueprint $table): void {
            if (! Schema::hasColumn('product_transfers', 'client_id')) {
                $table->foreignId('client_id')
                    ->nullable()
                    ->constrained('clients')
                    ->nullOnDelete()
                    ->after('sale_id');
            }

            if (! Schema::hasColumn('product_transfers', 'customer_invoice_reference')) {
                $table->string('customer_invoice_reference', 120)
                    ->nullable()
                    ->after('client_id');
            }

            if (! Schema::hasColumn('product_transfers', 'delivery_address')) {
                $table->text('delivery_address')
                    ->nullable()
                    ->after('customer_invoice_reference');
            }

            if (! Schema::hasColumn('product_transfers', 'delivery_recipient_name')) {
                $table->string('delivery_recipient_name')
                    ->nullable()
                    ->after('delivery_address');
            }

            if (! Schema::hasColumn('product_transfers', 'delivery_recipient_phone')) {
                $table->string('delivery_recipient_phone', 120)
                    ->nullable()
                    ->after('delivery_recipient_name');
            }

            if (! Schema::hasColumn('product_transfers', 'delivery_notes')) {
                $table->text('delivery_notes')
                    ->nullable()
                    ->after('delivery_recipient_phone');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('product_transfers')) {
            return;
        }

        Schema::table('product_transfers', function (Blueprint $table): void {
            if (Schema::hasColumn('product_transfers', 'delivery_notes')) {
                $table->dropColumn('delivery_notes');
            }

            if (Schema::hasColumn('product_transfers', 'delivery_recipient_phone')) {
                $table->dropColumn('delivery_recipient_phone');
            }

            if (Schema::hasColumn('product_transfers', 'delivery_recipient_name')) {
                $table->dropColumn('delivery_recipient_name');
            }

            if (Schema::hasColumn('product_transfers', 'delivery_address')) {
                $table->dropColumn('delivery_address');
            }

            if (Schema::hasColumn('product_transfers', 'customer_invoice_reference')) {
                $table->dropColumn('customer_invoice_reference');
            }

            if (Schema::hasColumn('product_transfers', 'client_id')) {
                $table->dropForeign(['client_id']);
                $table->dropColumn('client_id');
            }
        });
    }
};
