<?php

use App\Models\Pegawai;
use Illuminate\Support\Facades\Hash;

test('setelah login, redirect ke sso callback jika ada sso session', function () {
    $pegawai = Pegawai::factory()->create([
        'password' => Hash::make('password'),
    ]);

    session(['sso_app' => 'kepegawaian', 'sso_redirect' => 'http://localhost/dashboard']);

    $response = $this->post('/login', [
        'nip' => $pegawai->nip,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('sso.callback'));
});

test('setelah login tanpa sso session, redirect ke dashboard seperti biasa', function () {
    $pegawai = Pegawai::factory()->create([
        'password' => Hash::make('password'),
    ]);

    $response = $this->post('/login', [
        'nip' => $pegawai->nip,
        'password' => 'password',
    ]);

    $response->assertRedirect('/dashboard');
});
