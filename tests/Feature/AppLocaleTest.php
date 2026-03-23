<?php

test('aplicación usa locale español por defecto en entorno de pruebas', function () {
    expect(app()->getLocale())->toBe('es')
        ->and(config('app.fallback_locale'))->toBe('en');
});
