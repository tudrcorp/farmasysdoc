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
        Schema::table('partner_companies', function (Blueprint $table) {
            $table->date('agreement_expires_on')->nullable()->after('date_updated')
                ->comment('Fecha de vencimiento del convenio vigente');
        });

        DB::table('partner_companies')
            ->whereNotNull('date_updated')
            ->whereNotNull('date_created')
            ->whereColumn('date_updated', '>', 'date_created')
            ->update(['agreement_expires_on' => DB::raw('date_updated')]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('partner_companies', function (Blueprint $table) {
            $table->dropColumn('agreement_expires_on');
        });
    }
};
