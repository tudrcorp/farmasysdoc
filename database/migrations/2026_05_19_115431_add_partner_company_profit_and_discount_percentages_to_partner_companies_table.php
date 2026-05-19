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
        Schema::table('partner_companies', function (Blueprint $table): void {
            if (! Schema::hasColumn('partner_companies', 'profit_percentage_a')) {
                $table->decimal('profit_percentage_a', 12, 6)
                    ->nullable()
                    ->after('assigned_credit_limit')
                    ->comment('Primer porcentaje de ganancia del aliado');
            }

            if (! Schema::hasColumn('partner_companies', 'profit_percentage_b')) {
                $table->decimal('profit_percentage_b', 12, 6)
                    ->nullable()
                    ->after('profit_percentage_a')
                    ->comment('Segundo porcentaje de ganancia del aliado');
            }

            if (! Schema::hasColumn('partner_companies', 'discount_percentage')) {
                $table->decimal('discount_percentage', 12, 6)
                    ->nullable()
                    ->after('profit_percentage_b')
                    ->comment('Porcentaje de descuento del aliado');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('partner_companies', function (Blueprint $table): void {
            $columnsToDrop = array_values(array_filter([
                Schema::hasColumn('partner_companies', 'profit_percentage_a') ? 'profit_percentage_a' : null,
                Schema::hasColumn('partner_companies', 'profit_percentage_b') ? 'profit_percentage_b' : null,
                Schema::hasColumn('partner_companies', 'discount_percentage') ? 'discount_percentage' : null,
            ]));

            if ($columnsToDrop !== []) {
                $table->dropColumn($columnsToDrop);
            }
        });
    }
};
