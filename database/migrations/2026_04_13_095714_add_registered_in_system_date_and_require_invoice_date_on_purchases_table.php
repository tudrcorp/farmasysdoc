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
        if (! Schema::hasTable('purchases')) {
            return;
        }

        Schema::table('purchases', function (Blueprint $table): void {
            if (! Schema::hasColumn('purchases', 'registered_in_system_date')) {
                $table->date('registered_in_system_date')
                    ->nullable()
                    ->after('supplier_invoice_date')
                    ->comment('Fecha en que el usuario registró la compra en el sistema');
            }
        });

        $expr = $this->dateFromCreatedAtSqlExpression();

        if (Schema::hasColumn('purchases', 'registered_in_system_date')) {
            DB::statement("UPDATE purchases SET registered_in_system_date = {$expr} WHERE registered_in_system_date IS NULL");
        }

        if (Schema::hasColumn('purchases', 'supplier_invoice_date')) {
            DB::statement("UPDATE purchases SET supplier_invoice_date = {$expr} WHERE supplier_invoice_date IS NULL");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (! Schema::hasTable('purchases')) {
            return;
        }

        Schema::table('purchases', function (Blueprint $table): void {
            if (Schema::hasColumn('purchases', 'registered_in_system_date')) {
                $table->dropColumn('registered_in_system_date');
            }
        });
    }

    private function dateFromCreatedAtSqlExpression(): string
    {
        return match (Schema::getConnection()->getDriverName()) {
            'pgsql' => "(created_at AT TIME ZONE 'UTC')::date",
            default => 'DATE(created_at)',
        };
    }
};
