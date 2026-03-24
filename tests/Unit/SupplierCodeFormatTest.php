<?php

use App\Models\Supplier;

test('supplier code format uses prov prefix and zero padded id', function () {
    expect(Supplier::formatCode(1))->toBe('PROV-0001')
        ->and(Supplier::formatCode(42))->toBe('PROV-0042')
        ->and(Supplier::formatCode(12345))->toBe('PROV-12345');
});
