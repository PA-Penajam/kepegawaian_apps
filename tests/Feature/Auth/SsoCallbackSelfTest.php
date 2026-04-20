<?php

use App\Models\IamApplication;
use App\Models\Pegawai;

test('sso callback untuk kepegawaian sendiri redirect langsung tanpa code', function () {
    $pegawai = Pegawai::factory()->create();
    $redirectUrl = 'http://localhost/dashboard';

    session(['sso_app' => 'kepegawaian', 'sso_redirect' => $redirectUrl]);

    $response = $this->actingAs($pegawai)->get(route('sso.callback'));

    // Redirect ke URL tujuan langsung — tanpa ?code= apapun
    $response->assertRedirect($redirectUrl);
    $location = $response->headers->get('Location');
    expect($location)->not->toContain('?code=');
});

test('sso callback untuk aplikasi lain tetap generate code', function () {
    $app = IamApplication::factory()->create([
        'slug' => 'test-app-ext',
        'url' => 'http://test-app-ext.local',
        'is_active' => true,
    ]);

    $pegawai = Pegawai::factory()->create();
    $redirectUrl = 'http://test-app-ext.local/callback';

    session(['sso_app' => 'test-app-ext', 'sso_redirect' => $redirectUrl]);

    $response = $this->actingAs($pegawai)->get(route('sso.callback'));

    // Harus redirect dengan ?code=
    $location = $response->headers->get('Location');
    expect($location)->toContain('?code=');
});
