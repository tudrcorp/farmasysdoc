<?php

test('farmaadmin login page loads and shows brand slogan', function () {
    $response = $this->get('/farmaadmin/login');

    $response
        ->assertOk()
        ->assertSee('Nuestra gente, su bienestar', false);
});
