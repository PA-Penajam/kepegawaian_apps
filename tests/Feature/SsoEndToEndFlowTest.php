<?php

use App\Enums\StatusPegawai;
use App\Models\IamApplication;
use App\Models\Pegawai;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

test('gateway IdP mengarahkan pengguna tamu ke login SSO eksternal', function () {
    IamApplication::factory()->create([
        'slug' => 'persediaan',
        'url' => 'http://localhost:8000',
        'is_active' => true,
    ]);

    $this->get('/sso/login?'.http_build_query([
        'app' => 'persediaan',
        'redirect' => 'http://localhost:8000/auth/sso/callback',
        'state' => 'client-state',
    ]))
        ->assertRedirect(route('auth.sso.login'))
        ->assertSessionHas('sso_app', 'persediaan')
        ->assertSessionHas('sso_state', 'client-state');
});

test('gateway IdP menyelesaikan alur aplikasi konsumen setelah login SSO eksternal', function () {
    IamApplication::factory()->create([
        'slug' => 'persediaan',
        'url' => 'http://localhost:8000',
        'is_active' => true,
    ]);

    $pegawai = Pegawai::factory()->create([
        'nip' => '199001012020121009',
        'status_pegawai' => StatusPegawai::Aktif,
    ]);
    $redirectUrl = 'http://localhost:8000/auth/sso/callback';

    $this->get('/sso/login?'.http_build_query([
        'app' => 'persediaan',
        'redirect' => $redirectUrl,
        'state' => 'consumer-csrf-state',
    ]))->assertRedirect(route('auth.sso.login'));

    $this->get(route('auth.sso.login'))->assertRedirect();
    $oauthState = session('sso.oauth_state');

    Http::fake([
        config('sso.base_url').'/oauth/token' => Http::response([
            'token_type' => 'Bearer',
            'expires_in' => 3600,
            'access_token' => 'valid-access-token',
            'refresh_token' => 'valid-refresh-token',
        ]),
        config('sso.base_url').'/api/user' => Http::response([
            'data' => [
                'sub' => $pegawai->nip,
                'nip' => $pegawai->nip,
                'name' => $pegawai->nama_lengkap,
                'email' => $pegawai->email,
            ],
        ]),
    ]);

    $this->get(route('auth.sso.callback', [
        'code' => 'valid-auth-code',
        'state' => $oauthState['state'],
    ]))
        ->assertRedirect(route('sso.callback'));

    expect(Auth::id())->toBe($pegawai->id);

    $callbackResponse = $this->get(route('sso.callback'));
    $location = $callbackResponse->headers->get('Location');

    expect($location)->toStartWith($redirectUrl.'?code=')
        ->and($location)->toContain('&state=consumer-csrf-state');
});

test('callback gateway tanpa state kembali ke dashboard dengan peringatan', function () {
    $pegawai = Pegawai::factory()->create();

    $this->actingAs($pegawai)
        ->get(route('sso.callback'))
        ->assertRedirect(route('dashboard'))
        ->assertSessionHas('warning');
});

test('gateway IdP langsung menghasilkan code bagi pengguna yang sudah login', function () {
    IamApplication::factory()->create([
        'slug' => 'persediaan',
        'url' => 'http://localhost:8000',
        'is_active' => true,
    ]);

    $pegawai = Pegawai::factory()->create();
    $redirectUrl = 'http://localhost:8000/auth/sso/callback';

    $response = $this->actingAs($pegawai)->get('/sso/login?'.http_build_query([
        'app' => 'persediaan',
        'redirect' => $redirectUrl,
    ]));

    expect($response->headers->get('Location'))->toStartWith($redirectUrl.'?code=');
});
