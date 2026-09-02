<?php

use App\Models\Pegawai;

test('unauthenticated user diarahkan ke sso login bukan langsung ke login', function () {
    $response = $this->get('/dashboard');

    $location = $response->headers->get('Location');
    expect($location)->toContain('/auth/sso/login');
});

test('sso login tidak lagi menyertakan parameter redirect IAM', function () {
    $response = $this->get('/dashboard');

    $location = $response->headers->get('Location');
    expect($location)->toContain('/auth/sso/login');
    expect($location)->not->toContain('app=kepegawaian');
});

test('user yang sudah login tidak diarahkan ke sso login', function () {
    $pegawai = Pegawai::factory()->create();

    $response = $this->actingAs($pegawai)->get('/dashboard');

    $location = $response->headers->get('Location') ?? '';
    expect($location)->not->toContain('/auth/sso/login');
});
