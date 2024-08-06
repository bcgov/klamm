<?php

it('returns a successful response from the welcome page', function () {
    $response = $this->get(route('filament.home.pages.welcome'));

    $response->assertStatus(200);
});
