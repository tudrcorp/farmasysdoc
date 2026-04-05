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
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'delivery_recipient_document')) {
                $table->string('delivery_recipient_document', 64)
                    ->nullable()
                    ->after('delivery_phone')
                    ->comment('Cédula o RIF de quien recibe (pedidos aliado)');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('orders') || ! Schema::hasColumn('orders', 'delivery_recipient_document')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            $table->dropColumn('delivery_recipient_document');
        });
    }
};
