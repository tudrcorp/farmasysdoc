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
        Schema::table('partner_company_plan', function (Blueprint $table) {
            $table->decimal('authorized_amount_usd', 14, 2)->nullable()->after('partner_company_id')
                ->comment('Monto de consumo autorizado al aliado (planes de fondo de consumo) en USD');
            $table->decimal('authorized_amount_ves', 18, 2)->nullable()->after('authorized_amount_usd')
                ->comment('Monto de consumo autorizado en VES (sugerido con la tasa BCV, se guarda fijo)');
            $table->boolean('applies_intermediary_profit')->default(false)->after('authorized_amount_ves')
                ->comment('Si la negociación usa porcentaje de ganancia del intermediario');
            $table->decimal('intermediary_profit_percent', 7, 4)->nullable()->after('applies_intermediary_profit');
            $table->boolean('applies_partner_profit')->default(false)->after('intermediary_profit_percent')
                ->comment('Si la negociación usa porcentaje de ganancia de la compañía aliada');
            $table->decimal('partner_profit_percent', 7, 4)->nullable()->after('applies_partner_profit');
            $table->foreignId('created_by')->nullable()->after('partner_profit_percent')->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->after('created_by')->constrained('users')->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('partner_company_plan', function (Blueprint $table) {
            $table->dropConstrainedForeignId('created_by');
            $table->dropConstrainedForeignId('updated_by');
            $table->dropColumn([
                'authorized_amount_usd',
                'authorized_amount_ves',
                'applies_intermediary_profit',
                'intermediary_profit_percent',
                'applies_partner_profit',
                'partner_profit_percent',
            ]);
        });
    }
};
