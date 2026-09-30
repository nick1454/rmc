<?php

test('SPA route returns a successful response', function () {
    $response = $this->get('/');

    $response->assertOk();
    $response->assertViewIs('app');
});

test('SPA catch-all routes load app view', function () {
    $response = $this->get('/login');

    $response->assertOk();
    $response->assertViewIs('app');
});
