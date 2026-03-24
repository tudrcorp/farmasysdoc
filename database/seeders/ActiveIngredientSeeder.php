<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ActiveIngredientSeeder extends Seeder
{
    public function run(): void
    {
        $names = [
            'Paracetamol',
            'Ibuprofeno',
            'Diclofenaco',
            'Morfina',
            'Tramadol',
            'Diazepam',
            'Sertralina',
            'Fluoxetina',
            'Risperidona',
            'Carbamazepina',
            'Valproato',
            'Levetiracetam',
            'Enalapril',
            'Lisinopril',
            'Losartán',
            'Amlodipino',
            'Nifedipino',
            'Hidroclorotiazida',
            'Furosemida',
            'Espironolactona',
            'Digoxina',
            'Amiodarona',
            'Atenolol',
            'Metoprolol',
            'Propranolol',
            'Bisoprolol',
            'Carvedilol',
            'Diltiazem',
            'Verapamilo',
            'Rosuvastatina',
            'Ezetimiba',
            'Fenofibrato',
            'Amoxicilina/Clavulánico',
            'Amoxicilina',
            'Ceftriaxona',
            'Azitromicina',
            'Doxiciclina',
            'Metronidazol',
            'Fluconazol',
            'Aciclovir',
            'Oseltamivir',
            'Artemeter/Lumefantrina',
            'Quinina',
            'Ivermectina',
            'Albendazol',
            'Praziquantel',
            'Mebendazol',
            'Pirantel',
            'Simvastatina',
            'Warfarina',
            'Heparina',
            'Ácido acetilsalicílico',
            'Clopidogrel',
            'Insulina',
            'Metformina',
            'Glibenclamida',
            'Levotiroxina',
            'Hidrocortisona',
            'Prednisona',
            'Dexametasona',
            'Betametasona',
            'Omeprazol',
            'Pantoprazol',
            'Ranitidina',
            'Metoclopramida',
            'Loperamida',
            'Domperidona',
            'Bisacodilo',
            'Lactulosa',
            'Parafina líquida',
            'Hidróxido de aluminio',
            'Simeticona',
            'Dimenhidrinato',
            'Ondansetrón',
            'Difenhidramina',
            'Loratadina',
            'Cetirizina',
            'Salbutamol',
            'Ipratropio',
            'Budesonida',
            'Montelukast',
            'Dextrometorfano',
            'Ambroxol',
            'Guaifenesina',
            'Clorfenamina',
            'Pseudoefedrina',
            'Fenilefrina',
            'Clotrimazol',
            'Miconazol',
            'Ketoconazol',
            'Permetrina',
            'Peróxido de benzoilo',
            'Ácido salicílico',
            'Isotretinoína',
        ];

        $now = now();

        $rows = array_map(
            fn (string $name): array => [
                'name' => $name,
                'slug' => Str::slug($name),
                'created_at' => $now,
                'updated_at' => $now,
            ],
            $names,
        );

        DB::table('active_ingredients')->upsert(
            $rows,
            ['slug'],
            ['name', 'updated_at'],
        );
    }
}
