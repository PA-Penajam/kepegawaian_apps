<?php

use App\Services\Sso\DataTransferObjects\SsoTokenResult;
use App\Services\Sso\Services\SsoTokenStorage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Session;

describe('SsoTokenStorage', function () {
    beforeEach(function () {
        $this->storage = new SsoTokenStorage;
        Session::flush();
    });

    test('storeTokens menyimpan access token dan refresh token terenkripsi di session', function () {
        $tokenResult = new SsoTokenResult(
            accessToken: 'raw-access-token-12345',
            refreshToken: 'raw-refresh-token-67890',
            expiresIn: 3600,
            tokenType: 'Bearer',
        );

        $this->storage->storeTokens($tokenResult);

        $storedEncryptedRefreshToken = Session::get('sso.tokens.refresh_token');

        expect($storedEncryptedRefreshToken)->not->toBe('raw-refresh-token-67890');
        expect(Crypt::decryptString($storedEncryptedRefreshToken))->toBe('raw-refresh-token-67890');

        expect($this->storage->getAccessToken())->toBe('raw-access-token-12345');
        expect($this->storage->getRefreshToken())->toBe('raw-refresh-token-67890');
        expect(Session::get('sso.tokens.token_type'))->toBe('Bearer');
    });

    test('isTokenValid mengembalikan true jika token belum kedaluwarsa dan false jika expired', function () {
        $tokenResult = new SsoTokenResult(
            accessToken: 'token-abc',
            refreshToken: 'refresh-abc',
            expiresIn: 300,
            tokenType: 'Bearer',
        );

        $this->storage->storeTokens($tokenResult);
        expect($this->storage->isTokenValid())->toBeTrue();

        // Majukan waktu 400 detik
        Carbon::setTestNow(now()->addSeconds(400));
        expect($this->storage->isTokenValid())->toBeFalse();

        Carbon::setTestNow(); // Reset time
    });

    test('rotateTokens mengganti token secara atomic', function () {
        $initial = new SsoTokenResult(
            accessToken: 'initial-token',
            refreshToken: 'initial-refresh',
            expiresIn: 3600,
            tokenType: 'Bearer',
        );
        $this->storage->storeTokens($initial);

        $new = new SsoTokenResult(
            accessToken: 'rotated-token',
            refreshToken: 'rotated-refresh',
            expiresIn: 7200,
            tokenType: 'Bearer',
        );
        $this->storage->rotateTokens($new);

        expect($this->storage->getAccessToken())->toBe('rotated-token');
        expect($this->storage->getRefreshToken())->toBe('rotated-refresh');
    });

    test('clearTokens menghapus semua data token dari session', function () {
        $token = new SsoTokenResult(
            accessToken: 'some-token',
            refreshToken: 'some-refresh',
            expiresIn: 3600,
            tokenType: 'Bearer',
        );
        $this->storage->storeTokens($token);
        expect($this->storage->hasTokens())->toBeTrue();

        $this->storage->clearTokens();
        expect($this->storage->hasTokens())->toBeFalse();
        expect($this->storage->getAccessToken())->toBeNull();
        expect($this->storage->getRefreshToken())->toBeNull();
    });
});
