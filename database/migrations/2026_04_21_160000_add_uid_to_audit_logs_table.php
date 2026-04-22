<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('audit_logs')) {
            return;
        }

        if (Schema::hasColumn('audit_logs', 'uid')) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->string('uid', 26)->nullable()->unique()->after('id');
        });

        DB::table('audit_logs')
            ->whereNull('uid')
            ->orderBy('id')
            ->lazyById()
            ->each(function (object $row): void {
                DB::table('audit_logs')
                    ->where('id', $row->id)
                    ->update(['uid' => (string) Str::ulid()]);
            });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE audit_logs MODIFY uid VARCHAR(26) NOT NULL');
        } elseif ($driver === 'pgsql') {
            DB::statement('ALTER TABLE audit_logs ALTER COLUMN uid SET NOT NULL');
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('audit_logs') || ! Schema::hasColumn('audit_logs', 'uid')) {
            return;
        }

        Schema::table('audit_logs', function (Blueprint $table): void {
            $table->dropColumn('uid');
        });
    }
};
