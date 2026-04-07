<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('product_transfers')) {
            return;
        }

        Schema::table('product_transfers', function (Blueprint $table): void {
            if (! Schema::hasColumn('product_transfers', 'delivery_user_id')) {
                $table->foreignId('delivery_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete()
                    ->comment('Usuario delivery que tomó el traslado (estado En proceso)');
            }

            if (! Schema::hasColumn('product_transfers', 'in_progress_at')) {
                $table->timestamp('in_progress_at')
                    ->nullable()
                    ->comment('Momento en que el delivery pasó el traslado a En proceso');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('product_transfers')) {
            return;
        }

        Schema::table('product_transfers', function (Blueprint $table): void {
            if (Schema::hasColumn('product_transfers', 'in_progress_at')) {
                $table->dropColumn('in_progress_at');
            }

            if (Schema::hasColumn('product_transfers', 'delivery_user_id')) {
                $table->dropForeign(['delivery_user_id']);
                $table->dropColumn('delivery_user_id');
            }
        });
    }
};
