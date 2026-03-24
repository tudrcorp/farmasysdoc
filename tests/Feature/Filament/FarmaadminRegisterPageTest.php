<?php

test('farmaadmin register page loads and shows brand slogan', function () {
    $response = $this->get('/farmaadmin/register');

    $response
        ->assertOk()
        ->assertSee('Nuestra gente, su bienestar', false);
});
