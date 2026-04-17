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
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'delivery_latitude')) {
                $table->decimal('delivery_latitude', 10, 7)->nullable()->after('delivery_state');
            }
            if (! Schema::hasColumn('orders', 'delivery_longitude')) {
                $table->decimal('delivery_longitude', 10, 7)->nullable()->after('delivery_latitude');
            }
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            if (Schema::hasColumn('orders', 'delivery_longitude')) {
                $table->dropColumn('delivery_longitude');
            }
            if (Schema::hasColumn('orders', 'delivery_latitude')) {
                $table->dropColumn('delivery_latitude');
            }
        });
    }
};
