<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('historical_of_movements', function (Blueprint $table): void {
            $table->foreignId('partner_company_id')
                ->nullable()
                ->after('order_id')
                ->constrained('partner_companies')
                ->nullOnDelete();
        });

        DB::table('historical_of_movements')
            ->orderBy('id')
            ->chunkById(200, function ($rows): void {
                foreach ($rows as $row) {
                    $partnerCompanyId = DB::table('orders')
                        ->where('id', $row->order_id)
                        ->value('partner_company_id');

                    if ($partnerCompanyId !== null) {
                        DB::table('historical_of_movements')
                            ->where('id', $row->id)
                            ->update(['partner_company_id' => $partnerCompanyId]);
                    }
                }
            });

        Schema::table('historical_of_movements', function (Blueprint $table): void {
            $table->unique('order_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('historical_of_movements', function (Blueprint $table): void {
            $table->dropUnique(['order_id']);
        });

        Schema::table('historical_of_movements', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('partner_company_id');
        });
    }
};
