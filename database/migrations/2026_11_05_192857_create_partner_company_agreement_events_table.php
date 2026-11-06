<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_company_agreement_events', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_company_agreement_id');
            $table->foreign('partner_company_agreement_id', 'pca_events_agreement_fk')
                ->references('id')->on('partner_company_agreements')->cascadeOnDelete();
            $table->string('type', 40)->comment('App\Enums\PartnerAgreementEventType');
            $table->string('from_status', 30)->nullable();
            $table->string('to_status', 30)->nullable();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete()->comment('Vacío = proceso automático');
            $table->text('notes')->nullable();
            $table->json('meta')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['partner_company_agreement_id', 'created_at'], 'pca_events_agreement_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_company_agreement_events');
    }
};
