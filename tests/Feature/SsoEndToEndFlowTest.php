<?php

use App\Models\IamApplication;
use App\Models\Pegawai;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

test('sso login menyimpan state dan redirect ke fortify login', function () {
    $app = IamApplication::factory()->create([
        'slug' => 'persediaan',
        'url' => 'http://localhost:8000',
        'is_active' => true,
    ]);

    $response = $this->get('/sso/login?'.http_build_query([
        'app' => 'persediaan',
        'redirect' => 'http://localhost:8000/auth/sso/callback',
    ]));

    $response->assertRedirect(route('login'));
});

test('sso state bertahan melewati fortify login dan redirect ke callback', function () {
    $app = IamApplication::factory()->create([
        'slug' => 'persediaan',
        'url' => 'http://localhost:8000',
        'is_active' => true,
    ]);

    $pegawai = Pegawai::factory()->create([
        'password' => Hash::make('password'),
    ]);

    // Langkah 1: Akses /sso/login — simpan state, redirect ke /login
    $this->get('/sso/login?'.http_build_query([
        'app' => 'persediaan',
        'redirect' => 'http://localhost:8000/auth/sso/callback',
    ]));

    // Langkah 2: Login via Fortify
    $loginResponse = $this->post('/login', [
        'nip' => $pegawai->nip,
        'password' => 'password',
    ]);

    // Harus redirect ke sso.callback, BUKAN ke dashboard
    $loginResponse->assertRedirect(route('sso.callback'));
});

test('sso callback menghasilkan code dan redirect ke aplikasi client', function () {
    $app = IamApplication::factory()->create([
        'slug' => 'persediaan',
        'url' => 'http://localhost:8000',
        'is_active' => true,
    ]);

    $pegawai = Pegawai::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $redirectUrl = 'http://localhost:8000/auth/sso/callback';

    // Langkah 1: Akses /sso/login
    $this->get('/sso/login?'.http_build_query([
        'app' => 'persediaan',
        'redirect' => $redirectUrl,
    ]));

    // Langkah 2: Login via Fortify
    $this->post('/login', [
        'nip' => $pegawai->nip,
        'password' => 'password',
    ]);

    // Langkah 3: Follow redirect ke /sso/callback
    $callbackResponse = $this->get(route('sso.callback'));

    // Harus redirect ke persediaan callback URL dengan ?code=
    $location = $callbackResponse->headers->get('Location');
    expect($location)->toStartWith($redirectUrl);
    expect($location)->toContain('code=');
});

test('sso callback tanpa state redirect ke dashboard dengan warning', function () {
    $pegawai = Pegawai::factory()->create();

    // Langsung akses callback tanpa SSO flow sebelumnya
    $response = $this->actingAs($pegawai)->get(route('sso.callback'));

    $response->assertRedirect(route('dashboard'));
    $response->assertSessionHas('warning');
});

test('sso state bertahan melewati session regeneration', function () {
    $app = IamApplication::factory()->create([
        'slug' => 'persediaan',
        'url' => 'http://localhost:8000',
        'is_active' => true,
    ]);

    $pegawai = Pegawai::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $redirectUrl = 'http://localhost:8000/auth/sso/callback';

    // Simpan SSO state via cache-backed mechanism
    $this->get('/sso/login?'.http_build_query([
        'app' => 'persediaan',
        'redirect' => $redirectUrl,
    ]));

    // Login Fortify (ini melakukan session()->regenerate())
    $this->post('/login', [
        'nip' => $pegawai->nip,
        'password' => 'password',
    ]);

    // Pastikan callback bisa ambil state setelah regeneration
    $callbackResponse = $this->get(route('sso.callback'));

    $location = $callbackResponse->headers->get('Location');
    expect($location)->not->toEqual(route('dashboard'));
    expect($location)->toStartWith($redirectUrl);
});

test('sso state dengan user yang sudah login langsung generate code', function () {
    $app = IamApplication::factory()->create([
        'slug' => 'persediaan',
        'url' => 'http://localhost:8000',
        'is_active' => true,
    ]);

    $pegawai = Pegawai::factory()->create();
    $redirectUrl = 'http://localhost:8000/auth/sso/callback';

    // User sudah login → /sso/login langsung generate code
    $response = $this->actingAs($pegawai)->get('/sso/login?'.http_build_query([
        'app' => 'persediaan',
        'redirect' => $redirectUrl,
    ]));

    $location = $response->headers->get('Location');
    expect($location)->toStartWith($redirectUrl);
    expect($location)->toContain('code=');
});
