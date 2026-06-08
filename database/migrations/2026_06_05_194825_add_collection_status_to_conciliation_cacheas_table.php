<?php

use App\Enums\ConciliationCacheaCollectionStatus;
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
        Schema::table('conciliation_cacheas', function (Blueprint $table): void {
            $table->string('collection_status', 32)
                ->default(ConciliationCacheaCollectionStatus::PendingCollection->value)
                ->after('reference')
                ->index()
                ->comment('Cobro del resto pendiente de Cachea: por cobrar o ya recibido por la farmacia');
            $table->timestamp('collection_status_at')
                ->nullable()
                ->after('collection_status')
                ->comment('Momento en que se marcó monto recibido');
            $table->string('collection_status_by')
                ->nullable()
                ->after('collection_status_at')
                ->comment('Usuario que marcó monto recibido');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('conciliation_cacheas', function (Blueprint $table): void {
            $table->dropColumn([
                'collection_status',
                'collection_status_at',
                'collection_status_by',
            ]);
        });
    }
};
