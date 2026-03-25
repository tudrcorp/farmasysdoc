<?php

namespace Database\Seeders;

use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $suppliers = [
            [
                'code' => 'PROV-LAB-001',
                'legal_name' => 'Laboratorios Andinos S.A.S.',
                'trade_name' => 'Andino Pharma',
                'tax_id' => '901234561',
                'email' => 'compras@andinopharma.co',
                'phone' => '+57 601 7001001',
                'mobile_phone' => '+57 310 7001001',
                'website' => 'https://andinopharma.co',
                'address' => 'Zona Franca Fontibon Bodega 12',
                'city' => 'Bogota',
                'state' => 'Cundinamarca',
                'country' => 'Colombia',
                'contact_name' => 'Natalia Rojas',
                'contact_email' => 'natalia.rojas@andinopharma.co',
                'contact_phone' => '+57 310 7001001',
                'payment_terms' => 'Credito a 30 dias con cupo de reposicion semanal.',
                'notes' => 'Proveedor principal de analgesicos y antibioticos de alta rotacion.',
                'is_active' => true,
                'created_by' => 'SupplierSeeder',
                'updated_by' => 'SupplierSeeder',
            ],
            [
                'code' => 'PROV-DERM-002',
                'legal_name' => 'Dermocosmetica Integral S.A.',
                'trade_name' => 'DermiPlus',
                'tax_id' => '901234562',
                'email' => 'ventas@dermiplus.com.co',
                'phone' => '+57 604 6102233',
                'mobile_phone' => '+57 320 6102233',
                'website' => 'https://dermiplus.com.co',
                'address' => 'Carrera 48 # 14-55',
                'city' => 'Medellin',
                'state' => 'Antioquia',
                'country' => 'Colombia',
                'contact_name' => 'Santiago Londono',
                'contact_email' => 'santiago.londono@dermiplus.com.co',
                'contact_phone' => '+57 320 6102233',
                'payment_terms' => 'Contado con bonificacion por volumen en fin de mes.',
                'notes' => 'Maneja lineas de cuidado facial y corporal premium.',
                'is_active' => true,
                'created_by' => 'SupplierSeeder',
                'updated_by' => 'SupplierSeeder',
            ],
            [
                'code' => 'PROV-NUT-003',
                'legal_name' => 'NutriVida Distribuciones S.A.S.',
                'trade_name' => 'NutriVida',
                'tax_id' => '901234563',
                'email' => 'comercial@nutrivida.com.co',
                'phone' => '+57 602 5208899',
                'mobile_phone' => '+57 311 5208899',
                'website' => 'https://nutrivida.com.co',
                'address' => 'Avenida 6N # 24-80',
                'city' => 'Cali',
                'state' => 'Valle del Cauca',
                'country' => 'Colombia',
                'contact_name' => 'Paola Arango',
                'contact_email' => 'paola.arango@nutrivida.com.co',
                'contact_phone' => '+57 311 5208899',
                'payment_terms' => 'Credito a 45 dias. Entrega en frio para formulas sensibles.',
                'notes' => 'Especialista en nutricion enteral y suplementos pediatricos.',
                'is_active' => true,
                'created_by' => 'SupplierSeeder',
                'updated_by' => 'SupplierSeeder',
            ],
            [
                'code' => 'PROV-MED-004',
                'legal_name' => 'TecnoMed Equipos Clinicos S.A.S.',
                'trade_name' => 'TecnoMed',
                'tax_id' => '901234564',
                'email' => 'soporte@tecnomed.co',
                'phone' => '+57 601 7450099',
                'mobile_phone' => '+57 315 7450099',
                'website' => 'https://tecnomed.co',
                'address' => 'Calle 72 # 10-35 Oficina 503',
                'city' => 'Bogota',
                'state' => 'Cundinamarca',
                'country' => 'Colombia',
                'contact_name' => 'Carlos Mejia',
                'contact_email' => 'carlos.mejia@tecnomed.co',
                'contact_phone' => '+57 315 7450099',
                'payment_terms' => '50% anticipo y 50% contraentrega con garantia extendida.',
                'notes' => 'Equipos medicos con calibracion anual y soporte tecnico.',
                'is_active' => true,
                'created_by' => 'SupplierSeeder',
                'updated_by' => 'SupplierSeeder',
            ],
            [
                'code' => 'PROV-GEN-005',
                'legal_name' => 'Distribuciones Genericas del Caribe S.A.S.',
                'trade_name' => 'GenCaribe',
                'tax_id' => '901234565',
                'email' => 'pedidos@gencaribe.co',
                'phone' => '+57 605 3904400',
                'mobile_phone' => '+57 318 3904400',
                'website' => 'https://gencaribe.co',
                'address' => 'Via 40 # 67-120 Bodega 8',
                'city' => 'Barranquilla',
                'state' => 'Atlantico',
                'country' => 'Colombia',
                'contact_name' => 'Laura Pineda',
                'contact_email' => 'laura.pineda@gencaribe.co',
                'contact_phone' => '+57 318 3904400',
                'payment_terms' => 'Credito a 60 dias para medicamentos genericos.',
                'notes' => 'Catalogo amplio de genericos de rotacion media y alta.',
                'is_active' => true,
                'created_by' => 'SupplierSeeder',
                'updated_by' => 'SupplierSeeder',
            ],
        ];

        foreach ($suppliers as $supplier) {
            Supplier::query()->updateOrCreate(
                ['code' => $supplier['code']],
                $supplier,
            );
        }
    }
}
