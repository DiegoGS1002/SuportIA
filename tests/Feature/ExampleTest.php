<?php

test('the application redirects guests to the login screen', function () {
    $response = $this->get('/');

    $response->assertRedirect('/login');
});
