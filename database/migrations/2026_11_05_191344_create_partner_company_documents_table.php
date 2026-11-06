<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('partner_company_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('partner_company_id')->constrained()->cascadeOnDelete();
            $table->string('type', 40)->index()->comment('Valor de App\Enums\PartnerCompanyDocumentType');
            $table->string('file_path')->comment('Ruta en el disco privado (local)');
            $table->string('original_name')->nullable()->comment('Nombre original del archivo subido');
            $table->date('expires_at')->nullable()->comment('Vencimiento del documento, si aplica');
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('partner_company_documents');
    }
};
