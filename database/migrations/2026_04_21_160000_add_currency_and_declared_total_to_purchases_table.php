<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('purchases', function (Blueprint $table): void {
            if (! Schema::hasColumn('purchases', 'entry_currency')) {
                $table->string('entry_currency', 3)->default('USD')->after('branch_id');
            }
            if (! Schema::hasColumn('purchases', 'official_usd_ves_rate')) {
                $table->decimal('official_usd_ves_rate', 18, 2)->nullable()->after('entry_currency');
            }
            if (! Schema::hasColumn('purchases', 'declared_invoice_total')) {
                $table->decimal('declared_invoice_total', 16, 2)->nullable()->after('total');
            }
        });
    }

    public function down(): void
    {
        Schema::table('purchases', function (Blueprint $table): void {
            if (Schema::hasColumn('purchases', 'declared_invoice_total')) {
                $table->dropColumn('declared_invoice_total');
            }
            if (Schema::hasColumn('purchases', 'official_usd_ves_rate')) {
                $table->dropColumn('official_usd_ves_rate');
            }
            if (Schema::hasColumn('purchases', 'entry_currency')) {
                $table->dropColumn('entry_currency');
            }
        });
    }
};
