<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            BranchSeeder::class,
            ActiveIngredientSeeder::class,
            PresentationTypeSeeder::class,
            SupplierSeeder::class,
            ProductSeeder::class,
            InventorySeeder::class,
        ]);

        $defaultBranch = Branch::query()->where('is_headquarters', true)->first()
            ?? Branch::query()->first()
            ?? Branch::factory()->headquarters()->create([
                'name' => 'Sede principal',
            ]);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'branch_id' => $defaultBranch->id,
        ]);
    }
}
