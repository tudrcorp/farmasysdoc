<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Minutos desde creación del pedido hasta cierre con evidencia (solo panel admin muestra la columna).
     */
    public function up(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            if (! Schema::hasColumn('orders', 'delivery_fulfillment_duration_minutes')) {
                $table->unsignedInteger('delivery_fulfillment_duration_minutes')
                    ->nullable()
                    ->after('delivered_at')
                    ->comment('Minutos desde creación del pedido hasta entrega con evidencia');
            }
        });

        if (! Schema::hasColumn('orders', 'delivery_fulfillment_duration_minutes')) {
            return;
        }

        DB::table('orders')
            ->where('status', 'finalizado')
            ->whereNotNull('delivered_at')
            ->whereNotNull('created_at')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $created = Carbon::parse((string) $row->created_at);
                    $delivered = Carbon::parse((string) $row->delivered_at);
                    if ($delivered->lessThan($created)) {
                        continue;
                    }
                    $minutes = (int) $created->diffInMinutes($delivered);
                    DB::table('orders')->where('id', $row->id)->update([
                        'delivery_fulfillment_duration_minutes' => $minutes,
                    ]);
                }
            });
    }

    public function down(): void
    {
        if (! Schema::hasTable('orders')) {
            return;
        }

        Schema::table('orders', function (Blueprint $table): void {
            if (Schema::hasColumn('orders', 'delivery_fulfillment_duration_minutes')) {
                $table->dropColumn('delivery_fulfillment_duration_minutes');
            }
        });
    }
};
