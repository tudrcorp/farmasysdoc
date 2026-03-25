<?php

namespace Database\Seeders;

use App\Models\Branch;
use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            ActiveIngredientSeeder::class,
            PresentationTypeSeeder::class,
        ]);

        // User::factory(10)->create();

        $defaultBranch = Branch::query()->first()
            ?? Branch::factory()->create([
                'name' => 'Sede principal',
                'is_headquarters' => true,
            ]);

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
            'branch_id' => $defaultBranch->id,
        ]);
    }
}
