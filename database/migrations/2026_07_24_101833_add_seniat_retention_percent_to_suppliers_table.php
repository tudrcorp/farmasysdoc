<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('suppliers') && ! Schema::hasColumn('suppliers', 'seniat_retention_percent')) {
            Schema::table('suppliers', function (Blueprint $table): void {
                $table->decimal('seniat_retention_percent', 6, 2)
                    ->nullable()
                    ->after('tax_id')
                    ->comment('Porcentaje de retención SENIAT aplicable al proveedor (%)');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('suppliers') && Schema::hasColumn('suppliers', 'seniat_retention_percent')) {
            Schema::table('suppliers', function (Blueprint $table): void {
                $table->dropColumn('seniat_retention_percent');
            });
        }
    }
};
