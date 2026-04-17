<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('financial_settings') && ! Schema::hasColumn('financial_settings', 'igtf_rate_percent')) {
            Schema::table('financial_settings', function (Blueprint $table): void {
                $table->decimal('igtf_rate_percent', 6, 2)
                    ->default(3)
                    ->after('default_vat_rate_percent')
                    ->comment('IGTF sobre factura en USD efectivo (%)');
            });
        }

        if (Schema::hasTable('sales') && ! Schema::hasColumn('sales', 'igtf_total')) {
            Schema::table('sales', function (Blueprint $table): void {
                $table->decimal('igtf_total', 12, 2)
                    ->default(0)
                    ->after('tax_total')
                    ->comment('Impuesto IGTF (USD efectivo), no incluido en tax_total (IVA)');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sales') && Schema::hasColumn('sales', 'igtf_total')) {
            Schema::table('sales', function (Blueprint $table): void {
                $table->dropColumn('igtf_total');
            });
        }

        if (Schema::hasTable('financial_settings') && Schema::hasColumn('financial_settings', 'igtf_rate_percent')) {
            Schema::table('financial_settings', function (Blueprint $table): void {
                $table->dropColumn('igtf_rate_percent');
            });
        }
    }
};
