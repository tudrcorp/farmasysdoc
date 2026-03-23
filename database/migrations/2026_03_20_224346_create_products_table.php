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
        Schema::create('products', function (Blueprint $table) {
            $table->id()->comment('Identificador único del producto');
            $table->string('sku')->unique()->comment('Código SKU interno del artículo (único)');
            $table->string('barcode')->nullable()->unique()->comment('Código de barras o EAN (único si existe)');
            $table->string('name')->comment('Nombre comercial del producto');
            $table->string('slug')->nullable()->unique()->comment('Slug para URLs o búsqueda amigable');
            $table->text('description')->nullable()->comment('Descripción detallada del producto');
            $table->string('product_type')->index()->comment('Tipo: medicamento, perfumería, higiene, alimento, equipo médico, etc.');
            $table->string('brand')->nullable()->comment('Marca o laboratorio');
            $table->string('presentation')->nullable()->comment('Presentación comercial (ej. caja x 10 blísteres, frasco 120 ml)');
            $table->string('unit_of_measure')->default('unit')->comment('Unidad de medida de venta: unit, kg, l, box, pack, etc.');
            $table->decimal('unit_content', 10, 3)->nullable()->comment('Cantidad numérica por unidad de venta');
            $table->string('net_content_label')->nullable()->comment('Etiqueta de contenido neto (ej. 400 ml, 1 kg)');
            $table->decimal('sale_price', 12, 2)->comment('Precio de venta al público');
            $table->decimal('cost_price', 12, 2)->nullable()->comment('Costo de adquisición o valoración');
            $table->decimal('tax_rate', 5, 2)->default(0)->comment('Porcentaje de impuesto aplicable (IVA u otro)');
            $table->text('active_ingredient')->nullable()->comment('Principio(s) activo(s) del medicamento');
            $table->string('concentration')->nullable()->comment('Concentración del principio activo');
            $table->string('presentation_type')->nullable()->comment('Tipo de presentación farmacéutica: tableta, jarabe, cápsula, solución inyectable, crema, etc.');
            $table->boolean('requires_prescription')->default(false)->comment('Si requiere fórmula médica');
            $table->boolean('is_controlled_substance')->default(false)->comment('Si es medicamento controlado o psicotrópico');
            $table->string('health_registration_number')->nullable()->comment('Registro sanitario (ej. INVIMA)');
            $table->text('ingredients')->nullable()->comment('Ingredientes (alimentos o cosméticos)');
            $table->text('allergens')->nullable()->comment('Alérgenos declarados');
            $table->text('nutritional_information')->nullable()->comment('Información nutricional (tabla o texto)');
            $table->string('manufacturer')->nullable()->comment('Fabricante (equipos o productos)');
            $table->string('model')->nullable()->comment('Modelo o referencia de fabricante');
            $table->unsignedSmallInteger('warranty_months')->nullable()->comment('Garantía en meses (equipos)');
            $table->string('medical_device_class')->nullable()->comment('Clase del dispositivo médico: I, IIa, IIb, III');
            $table->boolean('requires_calibration')->default(false)->comment('Si requiere calibración periódica');
            $table->text('storage_conditions')->nullable()->comment('Condiciones de almacenamiento (temperatura, luz, etc.)');
            $table->boolean('is_active')->default(true)->comment('Si el producto está activo para venta o catálogo');
            $table->string('created_by')->comment('Usuario o sistema que creó el registro');
            $table->string('updated_by')->comment('Usuario o sistema que actualizó el registro por última vez');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('products');
    }
};
