<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('orders') && ! Schema::hasColumn('orders', 'partner_company_id')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->foreignId('partner_company_id')
                    ->nullable()
                    ->after('branch_id')
                    ->constrained('partner_companies')
                    ->nullOnDelete();
            });
        }

        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'partner_company_code')) {
            DB::table('orders')
                ->whereNotNull('partner_company_code')
                ->whereNull('partner_company_id')
                ->orderBy('id')
                ->chunkById(100, function ($rows): void {
                    foreach ($rows as $row) {
                        $partnerId = DB::table('partner_companies')
                            ->where('code', $row->partner_company_code)
                            ->value('id');
                        if ($partnerId !== null) {
                            DB::table('orders')
                                ->where('id', $row->id)
                                ->update(['partner_company_id' => $partnerId]);
                        }
                    }
                });
        }

        if (Schema::hasTable('users') && ! Schema::hasColumn('users', 'partner_company_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->foreignId('partner_company_id')
                    ->nullable()
                    ->after('branch_id')
                    ->constrained('partner_companies')
                    ->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('users') && Schema::hasColumn('users', 'partner_company_id')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('partner_company_id');
            });
        }

        if (Schema::hasTable('orders') && Schema::hasColumn('orders', 'partner_company_id')) {
            Schema::table('orders', function (Blueprint $table): void {
                $table->dropConstrainedForeignId('partner_company_id');
            });
        }
    }
};
