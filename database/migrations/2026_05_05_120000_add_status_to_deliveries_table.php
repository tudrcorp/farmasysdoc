<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('deliveries')) {
            return;
        }

        Schema::table('deliveries', function (Blueprint $table): void {
            if (! Schema::hasColumn('deliveries', 'status')) {
                $table->string('status', 32)
                    ->default('pendiente')
                    ->after('delivery_type')
                    ->comment('pendiente | en-proceso | completado');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('deliveries')) {
            return;
        }

        Schema::table('deliveries', function (Blueprint $table): void {
            if (Schema::hasColumn('deliveries', 'status')) {
                $table->dropColumn('status');
            }
        });
    }
};
