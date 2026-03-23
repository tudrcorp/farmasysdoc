<?php

use App\Models\Branch;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('branch can be created and has inventories relation', function () {
    $branch = Branch::factory()->headquarters()->create([
        'code' => 'SUC-001',
        'name' => 'Sede principal',
    ]);

    expect($branch->is_headquarters)->toBeTrue()
        ->and($branch->inventories()->count())->toBe(0);
});
