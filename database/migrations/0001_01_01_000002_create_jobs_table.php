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
        Schema::create('jobs', function (Blueprint $table) {
            $table->id()->comment('Identificador único del job en cola');
            $table->string('queue')->index()->comment('Nombre de la cola donde se encoló el trabajo');
            $table->longText('payload')->comment('Payload serializado del job (clase y datos)');
            $table->unsignedTinyInteger('attempts')->comment('Número de intentos de ejecución realizados');
            $table->unsignedInteger('reserved_at')->nullable()->comment('Timestamp Unix en que el worker reservó el job');
            $table->unsignedInteger('available_at')->comment('Timestamp Unix a partir del cual el job puede ejecutarse');
            $table->unsignedInteger('created_at')->comment('Timestamp Unix de creación del job en cola');
        });

        Schema::create('job_batches', function (Blueprint $table) {
            $table->string('id')->primary()->comment('UUID del lote de jobs');
            $table->string('name')->comment('Nombre descriptivo del lote');
            $table->integer('total_jobs')->comment('Cantidad total de jobs en el lote');
            $table->integer('pending_jobs')->comment('Jobs pendientes por ejecutar');
            $table->integer('failed_jobs')->comment('Jobs que fallaron');
            $table->longText('failed_job_ids')->comment('Lista de IDs de jobs fallidos');
            $table->mediumText('options')->nullable()->comment('Opciones serializadas del lote');
            $table->integer('cancelled_at')->nullable()->comment('Timestamp Unix de cancelación del lote');
            $table->integer('created_at')->comment('Timestamp Unix de creación del lote');
            $table->integer('finished_at')->nullable()->comment('Timestamp Unix de finalización del lote');
        });

        Schema::create('failed_jobs', function (Blueprint $table) {
            $table->id()->comment('Identificador único del registro de fallo');
            $table->string('uuid')->unique()->comment('UUID único del job fallido');
            $table->text('connection')->comment('Nombre de la conexión de cola utilizada');
            $table->text('queue')->comment('Nombre de la cola');
            $table->longText('payload')->comment('Payload del job que falló');
            $table->longText('exception')->comment('Traza o mensaje de la excepción');
            $table->timestamp('failed_at')->useCurrent()->comment('Momento en que se registró el fallo');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('jobs');
        Schema::dropIfExists('job_batches');
        Schema::dropIfExists('failed_jobs');
    }
};
