<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Deduplicación de avisos de vencimiento: un aviso por convenio y umbral (días antes).
     */
    public function up(): void
    {
        Schema::create('partner_company_agreement_notices', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_company_agreement_id');
            $table->foreign('partner_company_agreement_id', 'pca_notices_agreement_fk')
                ->references('id')->on('partner_company_agreements')->cascadeOnDelete();
            $table->unsignedSmallInteger('threshold_days');
            $table->timestamp('sent_at');

            $table->unique(['partner_company_agreement_id', 'threshold_days'], 'pca_notices_agreement_threshold_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_company_agreement_notices');
    }
};
