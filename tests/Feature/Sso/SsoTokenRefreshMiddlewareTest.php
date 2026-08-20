<?php

use App\Models\Pegawai;
use App\Services\Sso\Contracts\SsoTokenStorageInterface;
use App\Services\Sso\DataTransferObjects\SsoTokenResult;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;

describe('SsoTokenRefresh Middleware', function () {
    beforeEach(function () {
        Route::get('/test-sso-protected', function () {
            return response()->json(['status' => 'ok']);
        })->middleware(['web', 'sso.refresh']);

        $this->tokenStorage = app(SsoTokenStorageInterface::class);
        $this->pegawai = Pegawai::factory()->create();
    });

    test('tidak melakukan apa-apa jika request bukan sesi SSO', function () {
        $response = $this->actingAs($this->pegawai)->getJson('/test-sso-protected');

        $response->assertOk();
        $response->assertJson(['status' => 'ok']);
    });

    test('tidak me-refresh token jika sisa waktu expiry masih lama', function () {
        $this->actingAs($this->pegawai);

        $this->tokenStorage->storeTokens(new SsoTokenResult(
            accessToken: 'current-valid-token',
            refreshToken: 'current-refresh-token',
            expiresIn: 3600,
            tokenType: 'Bearer',
        ));

        Http::fake();

        $response = $this->getJson('/test-sso-protected');

        $response->assertOk();
        Http::assertNothingSent();
    });

    test('melakukan refresh token proaktif jika sisa waktu expiry kurang dari threshold', function () {
        $this->actingAs($this->pegawai);

        // Token akan expired dalam 30 detik (threshold default: 60 detik)
        $this->tokenStorage->storeTokens(new SsoTokenResult(
            accessToken: 'about-to-expire-token',
            refreshToken: 'valid-refresh-token',
            expiresIn: 30,
            tokenType: 'Bearer',
        ));

        Http::fake([
            config('sso.base_url').'/oauth/token' => Http::response([
                'token_type' => 'Bearer',
                'expires_in' => 3600,
                'access_token' => 'refreshed-access-token',
                'refresh_token' => 'refreshed-refresh-token',
            ], 200),
        ]);

        $response = $this->getJson('/test-sso-protected');

        $response->assertOk();
        expect($this->tokenStorage->getAccessToken())->toBe('refreshed-access-token');
        expect($this->tokenStorage->getRefreshToken())->toBe('refreshed-refresh-token');
    });

    test('force logout jika refresh token gagal dan token sudah expired', function () {
        $this->actingAs($this->pegawai);

        $this->tokenStorage->storeTokens(new SsoTokenResult(
            accessToken: 'expired-token',
            refreshToken: 'revoked-refresh-token',
            expiresIn: 10,
            tokenType: 'Bearer',
        ));

        // Majukan waktu sehingga token benar-benar expired
        Carbon::setTestNow(now()->addSeconds(20));

        Http::fake([
            config('sso.base_url').'/oauth/token' => Http::response([
                'error' => 'invalid_grant',
                'error_description' => 'Token has been revoked',
            ], 400),
        ]);

        $response = $this->getJson('/test-sso-protected');

        $response->assertStatus(401);
        $response->assertJson(['error' => 'session_expired']);
        expect($this->tokenStorage->getAccessToken())->toBeNull();

        Carbon::setTestNow(); // Reset time
    });
});
