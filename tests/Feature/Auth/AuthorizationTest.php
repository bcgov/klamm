<?php

test('guests cannot access admin panel', function () {
    $this->get('/admin')
        ->assertRedirect('/admin/login');
});
