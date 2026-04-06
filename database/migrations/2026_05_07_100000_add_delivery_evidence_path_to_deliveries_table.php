<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Fotografía de evidencia al cerrar la entrega (usuario de delivery).
     */
    public function up(): void
    {
        if (! Schema::hasTable('deliveries')) {
            return;
        }

        Schema::table('deliveries', function (Blueprint $table): void {
            if (! Schema::hasColumn('deliveries', 'delivery_evidence_path')) {
                $table->string('delivery_evidence_path', 512)
                    ->nullable()
                    ->after('order_snapshot')
                    ->comment('Imagen de evidencia de entrega (disco público)');
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('deliveries')) {
            return;
        }

        Schema::table('deliveries', function (Blueprint $table): void {
            if (Schema::hasColumn('deliveries', 'delivery_evidence_path')) {
                $table->dropColumn('delivery_evidence_path');
            }
        });
    }
};
