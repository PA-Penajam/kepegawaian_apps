<?php

use App\Models\IamApplication;
use App\Models\Pegawai;

it('callback redirect ke dashboard jika tidak ada SSO session', function () {
    $user     = Pegawai::factory()->create();
    $response = $this->actingAs($user)->get('/sso/callback');
    $response->assertRedirect(route('dashboard'));
});

it('callback generate SSO code dan redirect ke URL yang valid', function () {
    $app  = IamApplication::factory()->create(['is_active' => true, 'url' => 'http://att.local']);
    $user = Pegawai::factory()->create();

    session(['sso_app' => $app->slug, 'sso_redirect' => 'http://att.local/callback']);

    $response = $this->actingAs($user)->get('/sso/callback');

    $response->assertRedirect();
    $location = $response->headers->get('Location');
    $this->assertStringStartsWith('http://att.local/callback?code=', $location);
    // Pastikan SSO code disimpan di database
    $this->assertDatabaseCount('iam_sso_codes', 1);
});

it('callback redirect ke dashboard jika aplikasi tidak aktif', function () {
    $app  = IamApplication::factory()->create(['is_active' => false, 'url' => 'http://att.local']);
    $user = Pegawai::factory()->create();

    session(['sso_app' => $app->slug, 'sso_redirect' => 'http://att.local/callback']);

    $response = $this->actingAs($user)->get('/sso/callback');
    $response->assertRedirect(route('dashboard'));
});

it('callback membersihkan session setelah digunakan', function () {
    $app  = IamApplication::factory()->create(['is_active' => true, 'url' => 'http://att.local']);
    $user = Pegawai::factory()->create();

    session(['sso_app' => $app->slug, 'sso_redirect' => 'http://att.local/callback']);
    $this->actingAs($user)->get('/sso/callback');

    // Session harus sudah di-pull (kosong setelah callback)
    $this->assertNull(session('sso_app'));
    $this->assertNull(session('sso_redirect'));
});
