<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('partner_companies') || Schema::hasColumn('partner_companies', 'can_place_orders')) {
            return;
        }

        Schema::table('partner_companies', function (Blueprint $table): void {
            $table->boolean('can_place_orders')
                ->default(false)
                ->after('is_active')
                ->comment('Farmadoc habilita pedidos del inventario de Las Delicias para este aliado.');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('partner_companies') || ! Schema::hasColumn('partner_companies', 'can_place_orders')) {
            return;
        }

        Schema::table('partner_companies', function (Blueprint $table): void {
            $table->dropColumn('can_place_orders');
        });
    }
};
