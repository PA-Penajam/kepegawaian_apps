<?php

use App\Models\Pegawai;

test('unauthenticated user diarahkan ke sso login bukan langsung ke login', function () {
    $response = $this->get('/dashboard');

    $location = $response->headers->get('Location');
    expect($location)->toContain('/sso/login');
    expect($location)->toContain('app=kepegawaian');
});

test('sso login menyertakan parameter redirect yang benar', function () {
    $response = $this->get('/dashboard');

    $location = $response->headers->get('Location');
    expect($location)->toContain('redirect=');
});

test('user yang sudah login tidak diarahkan ke sso login', function () {
    $pegawai = Pegawai::factory()->create();

    $response = $this->actingAs($pegawai)->get('/dashboard');

    // Tidak redirect ke sso.login
    $location = $response->headers->get('Location') ?? '';
    expect($location)->not->toContain('/sso/login');
});
