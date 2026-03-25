<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        if (! Schema::hasColumn('sales', 'reference')) {
            Schema::table('sales', function (Blueprint $table): void {
                $table->string('reference')
                    ->nullable()
                    ->after('payment_ves')
                    ->comment('Referencia de pago (transferencias VES, pago móvil, Zelle, etc.)');
            });
        }

        if (Schema::hasColumn('sales', 'payment_reference')) {
            DB::table('sales')
                ->whereNull('reference')
                ->update([
                    'reference' => DB::raw('payment_reference'),
                ]);

            Schema::table('sales', function (Blueprint $table): void {
                $table->dropColumn('payment_reference');
            });
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasColumn('sales', 'payment_reference')) {
            Schema::table('sales', function (Blueprint $table): void {
                $table->string('payment_reference')
                    ->nullable()
                    ->after('payment_ves')
                    ->comment('Referencia (Zelle, transferencias VES, pago móvil, etc.)');
            });
        }

        if (Schema::hasColumn('sales', 'reference')) {
            DB::table('sales')
                ->whereNull('payment_reference')
                ->update([
                    'payment_reference' => DB::raw('reference'),
                ]);

            Schema::table('sales', function (Blueprint $table): void {
                $table->dropColumn('reference');
            });
        }
    }
};
