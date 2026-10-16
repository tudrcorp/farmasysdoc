<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sales_books', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('branch_id')->constrained('branches')->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->unsignedTinyInteger('half')->comment('1 = días 1–15, 2 = días 16–fin de mes');
            $table->date('period_start');
            $table->date('period_end');
            $table->unsignedSmallInteger('version')->default(1)->comment('Se incrementa cada vez que se cierra de nuevo tras una reapertura');
            $table->string('status', 16)->comment('SalesBookStatus');
            $table->longText('snapshot')->comment('Libro congelado al cerrar (JSON): filas, totales y resumen');
            $table->char('content_hash', 64)->comment('SHA-256 del snapshot');
            $table->unsignedInteger('z_reports_count')->default(0);
            $table->decimal('total_sales_ves', 16, 2)->default(0);
            $table->decimal('exempt_ves', 16, 2)->default(0);
            $table->decimal('taxable_base_ves', 16, 2)->default(0);
            $table->decimal('tax_ves', 16, 2)->default(0);
            $table->decimal('retained_ves', 16, 2)->default(0);
            $table->string('closed_by');
            $table->timestamp('closed_at');
            $table->string('reopened_by')->nullable();
            $table->timestamp('reopened_at')->nullable();
            $table->text('reopen_reason')->nullable();
            $table->timestamps();

            $table->unique(['branch_id', 'year', 'month', 'half', 'version']);
            $table->index(['branch_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sales_books');
    }
};
