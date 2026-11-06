<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_company_agreements', function (Blueprint $table) {
            $table->id();
            $table->string('number')->nullable()->unique()->comment('CONV-{año}-{id}');
            $table->foreignId('partner_company_id')->constrained()->restrictOnDelete();
            $table->foreignId('previous_agreement_id')->nullable()
                ->constrained('partner_company_agreements')->nullOnDelete()
                ->comment('Versión que esta renovación/modificación reemplaza');
            $table->string('type', 30)->comment('App\Enums\PartnerAgreementType');
            $table->string('status', 30)->default('borrador')->comment('App\Enums\PartnerAgreementStatus');
            $table->boolean('is_automatic')->default(false)->comment('Creado por renovación automática');

            $table->date('starts_on');
            $table->date('ends_on');
            $table->boolean('auto_renews')->default(false);
            $table->unsignedSmallInteger('renewal_term_months')->nullable()
                ->comment('Plazo de la renovación automática; vacío = mismo plazo del convenio');

            $table->string('payment_modality', 20)->comment('App\Enums\PartnerCompanyPaymentModality');
            $table->decimal('credit_limit_usd', 14, 2)->nullable();
            $table->decimal('discount_percentage', 12, 6)->nullable();
            $table->decimal('profit_percentage_a', 12, 6)->nullable();
            $table->text('terms')->nullable();
            $table->text('change_reason')->nullable();

            $table->string('signed_document_path')->nullable();
            $table->string('signed_document_name')->nullable();
            $table->date('signed_on')->nullable();

            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('submitted_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignId('rejected_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('rejected_at')->nullable();
            $table->text('rejection_reason')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->text('cancellation_reason')->nullable();
            $table->timestamp('activated_at')->nullable();
            $table->timestamp('closed_at')->nullable()->comment('Momento en que venció, fue reemplazado o anulado');
            $table->timestamps();

            $table->index(['partner_company_id', 'status']);
            $table->index(['status', 'ends_on']);
            $table->index(['status', 'starts_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_company_agreements');
    }
};
