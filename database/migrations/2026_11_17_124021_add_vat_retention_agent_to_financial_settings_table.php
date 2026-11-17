<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('financial_settings') || Schema::hasColumn('financial_settings', 'vat_retention_agent_active')) {
            return;
        }

        Schema::table('financial_settings', function (Blueprint $table): void {
            $table->boolean('vat_retention_agent_active')
                ->default(true)
                ->after('igtf_rate_percent')
                ->comment('La empresa actúa como agente de retención de IVA (SENIAT)');
            $table->date('vat_retention_suspended_from')
                ->nullable()
                ->after('vat_retention_agent_active')
                ->comment('Fecha de factura desde la que no se retiene IVA');
            $table->date('vat_retention_resumed_from')
                ->nullable()
                ->after('vat_retention_suspended_from')
                ->comment('Fecha de factura desde la que se vuelve a retener IVA');
        });

        DB::table('financial_settings')->where('id', 1)->update([
            'vat_retention_agent_active' => false,
            'vat_retention_suspended_from' => '2026-11-01',
            'vat_retention_resumed_from' => null,
        ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('financial_settings') || ! Schema::hasColumn('financial_settings', 'vat_retention_agent_active')) {
            return;
        }

        Schema::table('financial_settings', function (Blueprint $table): void {
            $table->dropColumn([
                'vat_retention_agent_active',
                'vat_retention_suspended_from',
                'vat_retention_resumed_from',
            ]);
        });
    }
};
