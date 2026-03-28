<?php

namespace Database\Seeders;

use App\Models\Client;
use Illuminate\Database\Seeder;

class ClientSeeder extends Seeder
{
    /**
     * Crea clientes de demostración para desarrollo y pruebas.
     */
    public function run(): void
    {
        $documentTypes = [
            'CC', 'CC', 'NIT', 'CC', 'CE', 'CC', 'PAS', 'CC', 'NIT', 'CC',
            'CC', 'TI', 'CC', 'CC', 'RUT', 'CC', 'CC', 'OTRO', 'CC', 'CC',
        ];

        Client::factory()
            ->count(20)
            ->sequence(...collect($documentTypes)->map(fn (string $type): array => [
                'document_type' => $type,
            ])->all())
            ->create([
                'created_by' => 'seeder',
                'updated_by' => 'seeder',
            ]);
    }
}
