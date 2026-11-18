<?php

namespace Database\Seeders;

use App\Models\ActiveIngredient;
use App\Models\Formulary;
use App\Models\FormularyCategory;
use App\Models\FormularyCategoryItem;
use App\Support\Products\ActiveIngredientCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Catálogos de la propuesta «Asistencia Farmacéutica Corporativa»: Opción Agudos (50 presentaciones)
 * y Opción Crónicos (las 50 agudas + 20 para patologías crónicas).
 *
 * Idempotente: solo agrega lo que falte; no borra ni revierte cambios hechos desde el panel.
 */
class CorporateFormulariesSeeder extends Seeder
{
    /**
     * Categorías con sus presentaciones: [principio activo (nombre del maestro), presentación].
     *
     * @var array<string, array{name: string, items: list<array{0: string, 1: string}>}>
     */
    private const ACUTE_CATEGORIES = [
        'CTG-ANALG' => ['name' => 'Analgésicos y antiinflamatorios', 'items' => [
            ['ACETAMINOFEN', '500 mg tabletas'],
            ['ACETAMINOFEN', '120 mg/5 mL jarabe'],
            ['Ibuprofeno', '400 mg tabletas'],
            ['Ibuprofeno', '100 mg/5 mL suspensión'],
            ['NAPROXENO', '250 mg tabletas'],
            ['Diclofenaco', 'Sódico 50 mg tabletas'],
            ['Diclofenaco', 'Potásico 1,8 mg/mL suspensión'],
        ]],
        'CTG-ALERG' => ['name' => 'Antialérgicos y nasales', 'items' => [
            ['Loratadina', '10 mg tabletas'],
            ['Loratadina', '5 mg/5 mL jarabe'],
            ['Cetirizina', '10 mg tabletas'],
            ['Cetirizina', '1 mg/mL solución oral'],
            ['CLORFENIRAMINA', '4 mg tabletas'],
            ['CLORURO DE SODIO', '0,9% solución nasal'],
            ['CLORHIDRATO DE OXIMETAZOLINA', '0,05% gotas/aerosol'],
        ]],
        'CTG-GASTR' => ['name' => 'Gastrointestinales', 'items' => [
            ['Sales de rehidratación oral', 'Sobres'],
            ['Omeprazol', '20 mg cápsulas'],
            ['Famotidina', '20 mg tabletas'],
            ['Metoclopramida', '10 mg tabletas'],
            ['Ondansetrón', '4 mg tabletas'],
            ['Simeticona', '40 mg/mL gotas'],
            ['BUTILBROMURO DE HIOSCINA', '10 mg tabletas'],
        ]],
        'CTG-RESPI' => ['name' => 'Respiratorios', 'items' => [
            ['Ambroxol', '15 mg/5 mL jarabe'],
            ['Ambroxol', '30 mg tabletas'],
            ['Acetilcisteína', '600 mg efervescentes'],
            ['Guaifenesina', '100 mg/5 mL jarabe'],
        ]],
        'CTG-ANTIB' => ['name' => 'Antibióticos y antiinfecciosos', 'items' => [
            ['Amoxicilina', '500 mg cápsulas'],
            ['Amoxicilina', '250 mg/5 mL suspensión'],
            ['Amoxicilina/Clavulánico', '875/125 mg tabletas'],
            ['Amoxicilina/Clavulánico', '400/57 mg/5 mL suspensión'],
            ['Cefalexina', '500 mg cápsulas'],
            ['Cefalexina', '250 mg/5 mL suspensión'],
            ['Azitromicina', '500 mg tabletas'],
            ['Azitromicina', '200 mg/5 mL suspensión'],
            ['Metronidazol', '500 mg tabletas'],
            ['NITROFURANTOINA', '100 mg cápsulas'],
            ['Trimetoprim/Sulfametoxazol', '160/800 mg tabletas'],
        ]],
        'CTG-DERMA' => ['name' => 'Dermatológicos', 'items' => [
            ['Clotrimazol', 'Crema 1%'],
            ['Miconazol', 'Crema 2%'],
            ['Hidrocortisona', 'Crema 1%'],
            ['Mupirocina', 'Ungüento 2%'],
            ['Óxido de zinc', 'Pasta o ungüento'],
        ]],
        'CTG-OFTAL' => ['name' => 'Oftalmológicos', 'items' => [
            ['Carboximetilcelulosa', 'Oftálmica 0,5%'],
            ['Hipromelosa', 'Oftálmica 0,3%'],
            ['TOBRAMICINA', 'Oftálmica 0,3%'],
        ]],
        'CTG-OTROS' => ['name' => 'Otros tratamientos', 'items' => [
            ['Dimenhidrinato', '50 mg tabletas'],
            ['Metocarbamol', '500 mg tabletas'],
            ['Prednisona', '5 mg tabletas'],
            ['Prednisolona', '15 mg/5 mL solución'],
            ['Albendazol', '400 mg tabletas'],
            ['Albendazol', '200 mg/5 mL suspensión'],
        ]],
    ];

    /**
     * @var array<string, array{name: string, items: list<array{0: string, 1: string}>}>
     */
    private const CHRONIC_CATEGORIES = [
        'CTG-HTA' => ['name' => 'Hipertensión arterial', 'items' => [
            ['Losartán', 'Potásico 50 mg'],
            ['Losartán', 'Potásico 100 mg'],
            ['Amlodipino', '5 mg'],
            ['Amlodipino', '10 mg'],
            ['Enalapril', '10 mg'],
            ['Hidroclorotiazida', '25 mg'],
            ['Losartán/Hidroclorotiazida', '50/12,5 mg'],
            ['Losartán/Hidroclorotiazida', '100/25 mg'],
            ['Valsartán/Amlodipino', '80/5 mg'],
        ]],
        'CTG-DM2' => ['name' => 'Diabetes tipo 2', 'items' => [
            ['Metformina', '500 mg'],
            ['Metformina', '850 mg'],
            ['Metformina', '1.000 mg'],
            ['Glimepirida', '2 mg'],
            ['Metformina/Glibenclamida', '500/2,5 mg'],
        ]],
        'CTG-DISLI' => ['name' => 'Dislipidemia', 'items' => [
            ['Atorvastatina', '10 mg'],
            ['Atorvastatina', '20 mg'],
            ['Simvastatina', '20 mg'],
        ]],
        'CTG-HIPOT' => ['name' => 'Hipotiroidismo', 'items' => [
            ['LEVOTIROXINA SODICA', '25 mcg'],
            ['LEVOTIROXINA SODICA', '50 mcg'],
            ['LEVOTIROXINA SODICA', '100 mcg'],
        ]],
    ];

    public function run(): void
    {
        DB::transaction(function (): void {
            $acuteIds = $this->seedCategories(self::ACUTE_CATEGORIES);
            $chronicIds = $this->seedCategories(self::CHRONIC_CATEGORIES);

            $this->seedFormulary(
                'CAT-AGUDOS',
                'Opción Agudos',
                'Asistencia farmacéutica corporativa: 50 presentaciones para tratamientos agudos.',
                $acuteIds,
            );

            $this->seedFormulary(
                'CAT-CRONICOS',
                'Opción Crónicos',
                'Asistencia farmacéutica corporativa: incluye las 50 presentaciones agudas más 20 para patologías crónicas.',
                [...$acuteIds, ...$chronicIds],
            );
        });
    }

    /**
     * @param  array<string, array{name: string, items: list<array{0: string, 1: string}>}>  $categories
     * @return list<int>
     */
    private function seedCategories(array $categories): array
    {
        $ids = [];

        foreach ($categories as $code => $definition) {
            $category = FormularyCategory::query()->where('code', $code)->first()
                ?? FormularyCategory::query()->where('name', $definition['name'])->first()
                ?? FormularyCategory::query()->create([
                    'code' => $code,
                    'name' => $definition['name'],
                    'is_active' => true,
                ]);

            foreach ($definition['items'] as $sort => [$ingredientName, $presentation]) {
                FormularyCategoryItem::query()->firstOrCreate(
                    [
                        'formulary_category_id' => $category->id,
                        'active_ingredient_id' => $this->ingredientId($ingredientName),
                        'presentation' => $presentation,
                    ],
                    ['sort' => $sort + 1],
                );
            }

            $ids[] = $category->id;
        }

        return $ids;
    }

    /**
     * @param  list<int>  $categoryIds
     */
    private function seedFormulary(string $code, string $name, string $description, array $categoryIds): void
    {
        $formulary = Formulary::query()->firstOrCreate(
            ['code' => $code],
            [
                'name' => $name,
                'description' => $description,
                'is_active' => true,
                'requires_prescription' => true,
                'requires_authorization' => true,
            ],
        );

        $formulary->categories()->syncWithoutDetaching($categoryIds);
    }

    /**
     * Busca el principio activo por slug (ignora mayúsculas y acentos) o lo crea en el maestro.
     */
    private function ingredientId(string $name): int
    {
        $existingId = ActiveIngredient::query()->where('slug', Str::slug($name))->value('id');

        if ($existingId !== null) {
            return (int) $existingId;
        }

        $createdName = ActiveIngredientCatalog::createUnique($name);

        return (int) ActiveIngredient::query()->where('name', $createdName)->value('id');
    }
}
