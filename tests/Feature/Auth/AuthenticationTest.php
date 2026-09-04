<?php

use App\Models\Pegawai;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

test('halaman login hanya menjadi pintu masuk SSO', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('auth/login')
            ->missing('canResetPassword'),
        );
});

test('login lokal ditolak meskipun kredensial valid', function () {
    $user = Pegawai::factory()->create();

    $response = $this->post(route('login.store'), [
        'nip' => $user->nip,
        'password' => 'password',
    ]);

    $this->assertGuest();
    $response
        ->assertRedirect(route('login'))
        ->assertSessionHas('error', 'Login lokal dinonaktifkan. Silakan masuk melalui SSO PA Penajam.');
});

test('login lokal melalui API ditolak dengan forbidden', function () {
    $user = Pegawai::factory()->create();

    $this->postJson(route('login.store'), [
        'nip' => $user->nip,
        'password' => 'password',
    ])
        ->assertForbidden()
        ->assertJson([
            'message' => 'Login lokal dinonaktifkan. Silakan masuk melalui SSO PA Penajam.',
        ]);

    $this->assertGuest();
});

test('login lokal Filament tidak tersedia', function () {
    expect(Route::has('filament.admin.auth.login'))->toBeFalse();
});

test('panel admin pengguna tamu diarahkan ke SSO', function () {
    $this->get('/admin')->assertRedirectContains(route('auth.sso.login'));
});
