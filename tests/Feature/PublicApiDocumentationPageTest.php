<?php

test('public api documentation page is accessible without authentication', function () {
    $response = $this->get(route('public.api-docs'));

    $response
        ->assertOk()
        ->assertSee('Documentacion API Aliados')
        ->assertSee('/api/external/inventory')
        ->assertSee('/api/external/orders')
        ->assertSee('Playground interactivo');
});
