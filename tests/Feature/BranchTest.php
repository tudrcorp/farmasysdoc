<?php

use App\Models\Branch;

test('branch can be created and has inventories relation', function () {
    $branch = Branch::factory()->headquarters()->create([
        'name' => 'Sede principal',
    ]);

    expect($branch->is_headquarters)->toBeTrue()
        ->and($branch->refresh()->code)->toBe('SUC-'.$branch->id)
        ->and($branch->inventories()->count())->toBe(0);
});
