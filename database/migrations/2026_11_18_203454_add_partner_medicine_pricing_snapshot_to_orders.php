<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Congela, al crear el pedido del aliado, el plan y la ganancia con la que se cobró cada medicamento.
     */
    public function up(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table): void {
                if (! Schema::hasColumn('orders', 'partner_prices_by_cost_profit')) {
                    $table->boolean('partner_prices_by_cost_profit')
                        ->default(false)
                        ->after('partner_company_code');
                }
                if (! Schema::hasColumn('orders', 'partner_pricing_plan_name')) {
                    $table->string('partner_pricing_plan_name')->nullable()->after('partner_prices_by_cost_profit');
                }
                if (! Schema::hasColumn('orders', 'partner_pricing_band_label')) {
                    $table->string('partner_pricing_band_label')->nullable()->after('partner_pricing_plan_name');
                }
                if (! Schema::hasColumn('orders', 'partner_pricing_profit_percent')) {
                    $table->decimal('partner_pricing_profit_percent', 12, 6)->nullable()->after('partner_pricing_band_label');
                }
                if (! Schema::hasColumn('orders', 'partner_pricing_affiliates_count')) {
                    $table->unsignedInteger('partner_pricing_affiliates_count')->nullable()->after('partner_pricing_profit_percent');
                }
            });
        }

        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table): void {
                if (! Schema::hasColumn('order_items', 'unit_cost')) {
                    $table->decimal('unit_cost', 12, 2)->nullable()->after('unit_price');
                }
                if (! Schema::hasColumn('order_items', 'profit_percent')) {
                    $table->decimal('profit_percent', 12, 6)->nullable()->after('unit_cost');
                }
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('orders')) {
            Schema::table('orders', function (Blueprint $table): void {
                $drops = array_values(array_filter([
                    Schema::hasColumn('orders', 'partner_pricing_affiliates_count') ? 'partner_pricing_affiliates_count' : null,
                    Schema::hasColumn('orders', 'partner_pricing_profit_percent') ? 'partner_pricing_profit_percent' : null,
                    Schema::hasColumn('orders', 'partner_pricing_band_label') ? 'partner_pricing_band_label' : null,
                    Schema::hasColumn('orders', 'partner_pricing_plan_name') ? 'partner_pricing_plan_name' : null,
                    Schema::hasColumn('orders', 'partner_prices_by_cost_profit') ? 'partner_prices_by_cost_profit' : null,
                ]));

                if ($drops !== []) {
                    $table->dropColumn($drops);
                }
            });
        }

        if (Schema::hasTable('order_items')) {
            Schema::table('order_items', function (Blueprint $table): void {
                $drops = array_values(array_filter([
                    Schema::hasColumn('order_items', 'profit_percent') ? 'profit_percent' : null,
                    Schema::hasColumn('order_items', 'unit_cost') ? 'unit_cost' : null,
                ]));

                if ($drops !== []) {
                    $table->dropColumn($drops);
                }
            });
        }
    }
};
