<?php

namespace Database\Seeders;

use App\Models\Branch;
use Illuminate\Database\Seeder;

class BranchSeeder extends Seeder
{
    /**
     * Tres sucursales de ejemplo (código SUC-{id} se asigna al crear el modelo).
     */
    public function run(): void
    {
        $branches = [
            [
                'name' => 'Sede principal — Centro',
                'legal_name' => 'Farmasys Doc S.A.S.',
                'tax_id' => '900123456',
                'email' => 'centro@example.com',
                'phone' => '+57 601 5550101',
                'mobile_phone' => '+57 300 5550101',
                'address' => 'Carrera 7 # 12-34',
                'city' => 'Bogotá',
                'state' => 'Cundinamarca',
                'country' => 'Colombia',
                'is_headquarters' => true,
                'is_active' => true,
                'notes' => 'Sede matriz y punto de referencia operativa.',
                'created_by' => 'BranchSeeder',
                'updated_by' => 'BranchSeeder',
            ],
            [
                'name' => 'Sucursal Chapinero',
                'legal_name' => null,
                'tax_id' => null,
                'email' => 'chapinero@example.com',
                'phone' => '+57 601 5550202',
                'mobile_phone' => null,
                'address' => 'Calle 60 # 7-20',
                'city' => 'Bogotá',
                'state' => 'Cundinamarca',
                'country' => 'Colombia',
                'is_headquarters' => false,
                'is_active' => true,
                'notes' => null,
                'created_by' => 'BranchSeeder',
                'updated_by' => 'BranchSeeder',
            ],
            [
                'name' => 'Sucursal Medellín — El Poblado',
                'legal_name' => null,
                'tax_id' => null,
                'email' => 'medellin@example.com',
                'phone' => '+57 604 5550303',
                'mobile_phone' => null,
                'address' => 'Carrera 43A # 5-113',
                'city' => 'Medellín',
                'state' => 'Antioquia',
                'country' => 'Colombia',
                'is_headquarters' => false,
                'is_active' => true,
                'notes' => null,
                'created_by' => 'BranchSeeder',
                'updated_by' => 'BranchSeeder',
            ],
        ];

        foreach ($branches as $attributes) {
            Branch::query()->firstOrCreate(
                ['name' => $attributes['name']],
                $attributes,
            );
        }
    }
}
