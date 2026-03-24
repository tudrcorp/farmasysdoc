<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class PresentationTypeSeeder extends Seeder
{
    public function run(): void
    {
        $types = [
            ['name' => 'Tableta/Comprimido', 'description' => 'Sólido comprimido, puede ser recubierto o no'],
            ['name' => 'Tableta efervescente', 'description' => 'Se disuelve en agua liberando CO2'],
            ['name' => 'Cápsula dura', 'description' => 'Gelatina con polvo/granulado en su interior'],
            ['name' => 'Cápsula blanda', 'description' => 'Gelatina con líquido/suspensión en su interior'],
            ['name' => 'Gragea', 'description' => 'Tableta pequeña con recubrimiento azucarado'],
            ['name' => 'Polvo para reconstituir', 'description' => 'Polvo que se mezcla con agua para formar suspensión'],
            ['name' => 'Supositorio', 'description' => 'Sólido que se funde a temperatura corporal'],
            ['name' => 'Óvulo', 'description' => 'Forma sólida para uso vaginal'],
            ['name' => 'Pastilla', 'description' => 'Forma plana para disolver lentamente en boca'],
            ['name' => 'Comprimido sublingual', 'description' => 'Se coloca bajo la lengua para absorción rápida'],
            ['name' => 'Jarabe', 'description' => 'Solución viscosa con azúcar/edulcorante'],
            ['name' => 'Solución oral', 'description' => 'Líquido homogéneo sin partículas'],
            ['name' => 'Suspensión oral', 'description' => 'Líquido con partículas insolubles (agitar antes de usar)'],
            ['name' => 'Emulsión', 'description' => 'Mezcla de dos líquidos inmiscibles (aceite/agua)'],
            ['name' => 'Elixir', 'description' => 'Solución hidroalcohólica aromatizada'],
            ['name' => 'Tintura', 'description' => 'Extracto alcohólico de plantas'],
            ['name' => 'Gotas oftálmicas', 'description' => 'Solución estéril para ojos'],
            ['name' => 'Gotas óticas', 'description' => 'Solución estéril para oídos'],
            ['name' => 'Gotas nasales', 'description' => 'Solución para aplicación nasal'],
            ['name' => 'Gotas orales', 'description' => 'Solución en cuentagotas para dosificación precisa'],
            ['name' => 'Linimento', 'description' => 'Líquido para frotar en piel/músculos'],
            ['name' => 'Loción', 'description' => 'Emulsión líquida para piel/cabello'],
            ['name' => 'Ampolla', 'description' => 'Envase de vidrio hermético de un solo uso'],
            ['name' => 'Frasco ampolla', 'description' => 'Envase de vidrio para reconstituir o dosis múltiples'],
            ['name' => 'Jeringa precargada', 'description' => 'Jeringa lista para usar con dosis exacta'],
            ['name' => 'Vial', 'description' => 'Frasco con tapón de goma para múltiples extracciones'],
            ['name' => 'Solución inyectable', 'description' => 'Líquido listo para inyectar'],
            ['name' => 'Suspensión inyectable', 'description' => 'Partículas insolubles en vehículo (agitar)'],
            ['name' => 'Emulsión inyectable', 'description' => 'Grasas emulsionadas para nutrición parenteral'],
            ['name' => 'Polvo liofilizado', 'description' => 'Sustancia deshidratada que requiere reconstitución'],
            ['name' => 'Crema', 'description' => 'Emulsión O/A (aceite en agua), absorción rápida'],
            ['name' => 'Pomada/Ungüento', 'description' => 'Base grasa (A/O), efecto oclusivo'],
            ['name' => 'Gel', 'description' => 'Base acuosa transparente con espesante'],
            ['name' => 'Locución', 'description' => 'Líquido espeso para aplicación tópica'],
            ['name' => 'Espuma', 'description' => 'Formulación aerada para fácil aplicación'],
            ['name' => 'Parche transdérmico', 'description' => 'Sistema de liberación controlada a través de piel'],
            ['name' => 'Bálsamo', 'description' => 'Preparación espesa con ceras naturales'],
            ['name' => 'Cataplasma', 'description' => 'Pasta espesa para aplicación localizada'],
            ['name' => 'Aerosol presurizado (MDI)', 'description' => 'Envase con propulsor para pulverización'],
            ['name' => 'Polvo seco (DPI)', 'description' => 'Cápsulas/cartuchos con polvo para inhalar'],
            ['name' => 'Nebulizador', 'description' => 'Solución convertida en aerosol por dispositivo'],
            ['name' => 'Espuma nasal', 'description' => 'Aerosol para cavidad nasal'],
            ['name' => 'Spray nasal', 'description' => 'Pulverización fina para nariz'],
            ['name' => 'Colutorio/Enjuague bucal', 'description' => 'Solución para enjuagar boca'],
            ['name' => 'Tableta bucal', 'description' => 'Se disuelve lentamente en boca'],
            ['name' => 'Pasta dental medicada', 'description' => 'Dentífrico con principios activos'],
            ['name' => 'Cemento dental', 'description' => 'Material para fijación/restauración'],
            ['name' => 'Barniz fluorado', 'description' => 'Aplicación profesional de flúor'],
            ['name' => 'Tableta de liberación prolongada (LP/ER)', 'description' => 'Libera fármaco gradualmente'],
            ['name' => 'Tableta de liberación modificada', 'description' => 'Patrón específico de liberación'],
            ['name' => 'Tableta orodispersable', 'description' => 'Se disuelve instantáneamente en saliva'],
            ['name' => 'Sistema de liberación osmótica', 'description' => 'Bomba osmótica controlada'],
            ['name' => 'Nanopartículas', 'description' => 'Partículas <1000nm para mejor absorción'],
            ['name' => 'Liposomas', 'description' => 'Vesículas lipídicas para transporte'],
        ];

        $now = now();
        $rows = array_map(
            fn (array $type): array => [
                'name' => $type['name'],
                'description' => $type['description'],
                'slug' => Str::slug(Str::lower($type['name'])),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $types,
        );

        DB::table('presentation_types')->upsert(
            $rows,
            ['slug'],
            ['name', 'description', 'updated_at'],
        );
    }
}
