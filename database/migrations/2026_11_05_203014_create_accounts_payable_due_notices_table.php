<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deduplicación de avisos de vencimiento de CxP: un aviso por cuenta por pagar y umbral (5 o 3 días).
     */
    public function up(): void
    {
        Schema::create('accounts_payable_due_notices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('accounts_payable_id')->constrained('accounts_payables')->cascadeOnDelete();
            $table->unsignedSmallInteger('threshold_days');
            $table->date('due_at')->comment('Vencimiento avisado (si cambia, se vuelve a avisar)');
            $table->timestamp('sent_at');

            $table->unique(['accounts_payable_id', 'threshold_days', 'due_at'], 'ap_due_notices_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts_payable_due_notices');
    }
};
