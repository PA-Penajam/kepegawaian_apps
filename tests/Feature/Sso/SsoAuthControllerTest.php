<?php

use App\Enums\StatusPegawai;
use App\Models\Pegawai;
use App\Services\Sso\Contracts\SsoTokenStorageInterface;
use App\Services\Sso\DataTransferObjects\SsoTokenResult;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Http;

describe('SsoAuthController', function () {
    test('login me-redirect ke SSO Authorization URL dan menyimpan state & PKCE di session', function () {
        $response = $this->get(route('auth.sso.login'));

        $response->assertRedirect();
        $targetUrl = $response->headers->get('Location');
        expect($targetUrl)->toContain(config('sso.base_url').'/oauth/authorize');
        expect($targetUrl)->toContain('client_id='.config('sso.client_id'));
        expect($targetUrl)->toContain('response_type=code');
        expect($targetUrl)->toContain('code_challenge_method=S256');

        $sessionState = session('sso.oauth_state');
        expect($sessionState)->toBeArray();
        expect($sessionState)->toHaveKeys(['state', 'code_verifier', 'expires_at']);
        expect($targetUrl)->toContain('state='.$sessionState['state']);
    });

    test('callback menangani error yang dikirimkan oleh SSO server', function () {
        $response = $this->get(route('auth.sso.callback', [
            'error' => 'access_denied',
            'error_description' => 'The user denied the request.',
        ]));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error', 'The user denied the request.');
    });

    test('callback menolak request tanpa session state (403)', function () {
        $response = $this->get(route('auth.sso.callback', [
            'code' => 'test-code',
            'state' => 'test-state',
        ]));

        $response->assertForbidden();
    });

    test('callback mengembalikan 403 jika state CSRF tidak cocok dengan session', function () {
        session(['sso.oauth_state' => [
            'state' => 'valid-state-12345',
            'code_verifier' => 'test-verifier',
            'expires_at' => now()->addMinutes(10)->toIso8601String(),
        ]]);

        $response = $this->get(route('auth.sso.callback', [
            'code' => 'auth-code-123',
            'state' => 'forged-state-67890',
        ]));

        $response->assertForbidden();
    });

    test('callback mengembalikan 403 jika state oauth telah kedaluwarsa', function () {
        session(['sso.oauth_state' => [
            'state' => 'valid-state-12345',
            'code_verifier' => 'test-verifier',
            'expires_at' => now()->subMinutes(15)->toIso8601String(),
        ]]);

        $response = $this->get(route('auth.sso.callback', [
            'code' => 'auth-code-123',
            'state' => 'valid-state-12345',
        ]));

        $response->assertForbidden();
    });

    test('callback me-redirect ke login jika penukaran kode token gagal', function () {
        session(['sso.oauth_state' => [
            'state' => 'valid-state-12345',
            'code_verifier' => 'test-verifier',
            'expires_at' => now()->addMinutes(10)->toIso8601String(),
        ]]);

        Http::fake([
            config('sso.base_url').'/oauth/token' => Http::response([
                'error' => 'invalid_grant',
                'message' => 'Invalid authorization code',
            ], 400),
        ]);

        $response = $this->get(route('auth.sso.callback', [
            'code' => 'invalid-auth-code',
            'state' => 'valid-state-12345',
        ]));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error');
    });

    test('callback menolak jika pegawai tidak ditemukan di database SIMPEG', function () {
        $nip = '199001012020121001';

        session(['sso.oauth_state' => [
            'state' => 'valid-state-12345',
            'code_verifier' => 'test-verifier',
            'expires_at' => now()->addMinutes(10)->toIso8601String(),
        ]]);

        Http::fake([
            config('sso.base_url').'/oauth/token' => Http::response([
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'access_token' => 'valid-access-token',
                'refresh_token' => 'valid-refresh-token',
            ], 200),
            config('sso.base_url').'/api/user' => Http::response([
                'data' => [
                    'sub' => $nip,
                    'nip' => $nip,
                    'name' => 'Budi Utomo',
                    'email' => 'budi@pa-penajam.go.id',
                ],
            ], 200),
        ]);

        $response = $this->get(route('auth.sso.callback', [
            'code' => 'valid-auth-code',
            'state' => 'valid-state-12345',
        ]));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error', "NIP {$nip} tidak terdaftar dalam sistem kepegawaian (SIMPEG).");
        expect(Auth::check())->toBeFalse();
    });

    test('callback menolak jika pegawai ditemukan tetapi statusnya tidak Aktif', function () {
        $nip = '199001012020121002';
        $pegawai = Pegawai::factory()->create([
            'nip' => $nip,
            'status_pegawai' => StatusPegawai::Pensiun,
        ]);

        session(['sso.oauth_state' => [
            'state' => 'valid-state-12345',
            'code_verifier' => 'test-verifier',
            'expires_at' => now()->addMinutes(10)->toIso8601String(),
        ]]);

        Http::fake([
            config('sso.base_url').'/oauth/token' => Http::response([
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'access_token' => 'valid-access-token',
                'refresh_token' => 'valid-refresh-token',
            ], 200),
            config('sso.base_url').'/api/user' => Http::response([
                'data' => [
                    'sub' => $nip,
                    'nip' => $nip,
                    'name' => $pegawai->nama_lengkap,
                    'email' => $pegawai->email,
                ],
            ], 200),
        ]);

        $response = $this->get(route('auth.sso.callback', [
            'code' => 'valid-auth-code',
            'state' => 'valid-state-12345',
        ]));

        $response->assertRedirect(route('login'));
        $response->assertSessionHas('error', 'Akun Pegawai tidak aktif. Silakan hubungi administrator kepegawaian.');
        expect(Auth::check())->toBeFalse();
    });

    test('callback berhasil mengautentikasi pegawai aktif dan mengarahkan ke dashboard', function () {
        $nip = '199001012020121003';
        $pegawai = Pegawai::factory()->create([
            'nip' => $nip,
            'status_pegawai' => StatusPegawai::Aktif,
        ]);

        session(['sso.oauth_state' => [
            'state' => 'valid-state-12345',
            'code_verifier' => 'test-verifier',
            'expires_at' => now()->addMinutes(10)->toIso8601String(),
        ]]);

        Http::fake([
            config('sso.base_url').'/oauth/token' => Http::response([
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'access_token' => 'valid-access-token',
                'refresh_token' => 'valid-refresh-token',
            ], 200),
            config('sso.base_url').'/api/user' => Http::response([
                'data' => [
                    'sub' => $nip,
                    'nip' => $nip,
                    'name' => $pegawai->nama_lengkap,
                    'email' => $pegawai->email,
                ],
            ], 200),
        ]);

        $response = $this->get(route('auth.sso.callback', [
            'code' => 'valid-auth-code',
            'state' => 'valid-state-12345',
        ]));

        $response->assertRedirect('/dashboard');
        expect(Auth::check())->toBeTrue();
        expect(Auth::id())->toBe($pegawai->id);

        $storage = app(SsoTokenStorageInterface::class);
        expect($storage->getAccessToken())->toBe('valid-access-token');
        expect($storage->getRefreshToken())->toBe('valid-refresh-token');
        expect(session('sso.user.nip'))->toBe($nip);
    });

    test('logout membersihkan session SSO dan mengeluarkan akun user', function () {
        $pegawai = Pegawai::factory()->create();
        $this->actingAs($pegawai);

        $storage = app(SsoTokenStorageInterface::class);
        $storage->storeTokens(new SsoTokenResult(
            accessToken: 'test-token',
            refreshToken: 'test-refresh',
            expiresIn: 3600,
            tokenType: 'Bearer',
        ));

        Http::fake([
            config('sso.base_url').'/api/logout' => Http::response(['message' => 'Logged out'], 200),
        ]);

        $response = $this->post(route('auth.sso.logout'));

        $response->assertRedirect('/');
        expect(Auth::check())->toBeFalse();
        expect($storage->getAccessToken())->toBeNull();
    });
});
