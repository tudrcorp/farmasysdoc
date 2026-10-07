<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventories', function (Blueprint $table): void {
            $table->decimal('branch_special_price', 14, 2)->nullable()->after('final_price_with_vat')
                ->comment('Precio de venta especial de esta sucursal (USD sin IVA); manda sobre el precio calculado y el precio directo');
            $table->timestamp('branch_special_price_set_at')->nullable()->after('branch_special_price');
            $table->string('branch_special_price_set_by')->nullable()->after('branch_special_price_set_at');
        });
    }

    public function down(): void
    {
        Schema::table('inventories', function (Blueprint $table): void {
            $table->dropColumn(['branch_special_price', 'branch_special_price_set_at', 'branch_special_price_set_by']);
        });
    }
};
