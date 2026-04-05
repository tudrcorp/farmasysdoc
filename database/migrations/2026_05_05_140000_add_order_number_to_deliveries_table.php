<?php

use App\Models\Delivery;
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
            if (! Schema::hasColumn('deliveries', 'order_number')) {
                $table->string('order_number', 255)
                    ->nullable()
                    ->after('order_id')
                    ->index()
                    ->comment('Denormalizado desde orders.order_number para búsqueda y acciones en tabla');
            }
        });

        if (Schema::hasColumn('deliveries', 'order_number')) {
            Delivery::query()
                ->whereNull('order_number')
                ->whereNotNull('order_id')
                ->with(['order:id,order_number'])
                ->chunkById(100, function ($deliveries): void {
                    foreach ($deliveries as $delivery) {
                        $num = $delivery->order?->order_number;
                        if (filled($num)) {
                            $delivery->forceFill(['order_number' => $num])->saveQuietly();
                        }
                    }
                });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('deliveries')) {
            return;
        }

        Schema::table('deliveries', function (Blueprint $table): void {
            if (Schema::hasColumn('deliveries', 'order_number')) {
                $table->dropColumn('order_number');
            }
        });
    }
};
