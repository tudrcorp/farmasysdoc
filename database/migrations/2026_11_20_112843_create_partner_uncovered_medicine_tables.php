<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('partner_uncovered_medicine_connections', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_company_id');
            $table->unique('partner_company_id', 'partner_um_conn_company_unique');
            $table->foreign('partner_company_id', 'partner_um_conn_company_fk')->references('id')->on('partner_companies')->cascadeOnDelete();
            $table->text('token')->nullable()->comment('Token Bearer de Tu Dr. Group, cifrado con la APP_KEY');
            $table->string('sync_status', 32)->default('idle')->comment('idle, running, succeeded o failed');
            $table->uuid('sync_run_id')->nullable()->comment('Identificador de la actualización en curso');
            $table->timestamp('sync_started_at')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->text('last_error')->nullable();
            $table->unsignedInteger('rate_limit_limit')->nullable()->comment('X-RateLimit-Limit de la última respuesta');
            $table->unsignedInteger('rate_limit_remaining')->nullable()->comment('X-RateLimit-Remaining de la última respuesta');
            $table->json('profile')->nullable()->comment('Respuesta saneada de GET /perfil');
            $table->timestamps();
        });

        Schema::create('partner_uncovered_medicines', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('partner_company_id');
            $table->foreign('partner_company_id', 'partner_um_company_fk')->references('id')->on('partner_companies')->cascadeOnDelete();
            $table->unsignedBigInteger('external_id')->comment('id estable de la API de Tu Dr. Group');
            $table->string('case_number')->nullable();
            $table->unsignedBigInteger('coordination_id')->nullable();
            $table->string('coordination_reference')->nullable();
            $table->string('coordination_status')->nullable();
            $table->text('patient_name')->nullable()->comment('Cifrado');
            $table->text('patient_national_id')->nullable()->comment('Cifrado');
            $table->text('patient_phone')->nullable()->comment('Cifrado');
            $table->string('patient_state')->nullable();
            $table->string('patient_city')->nullable();
            $table->string('medicine_name');
            $table->text('indications')->nullable();
            $table->string('quantity_label')->nullable();
            $table->string('duration_label')->nullable();
            $table->string('priority')->nullable();
            $table->string('management_status')->nullable();
            $table->boolean('covered')->default(false);
            $table->string('inventory_status')->nullable();
            $table->timestamp('registered_at')->nullable();
            $table->timestamp('remote_updated_at')->nullable();
            $table->timestamp('synced_at')->nullable();
            $table->timestamps();

            $table->unique(['partner_company_id', 'external_id'], 'partner_um_company_external_uq');
            $table->index(['partner_company_id', 'medicine_name'], 'partner_um_company_medicine_idx');
            $table->index(['partner_company_id', 'management_status'], 'partner_um_company_status_idx');
            $table->index(['partner_company_id', 'inventory_status'], 'partner_um_company_inventory_idx');
            $table->index(['partner_company_id', 'registered_at'], 'partner_um_company_registered_idx');
            $table->index(['partner_company_id', 'case_number'], 'partner_um_company_case_idx');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('partner_uncovered_medicines');
        Schema::dropIfExists('partner_uncovered_medicine_connections');
    }
};
