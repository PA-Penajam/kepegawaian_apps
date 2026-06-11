<?php

use App\Keycloak\DataTransferObjects\TokenResult;
use App\Keycloak\Services\KeycloakTokenStorage;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Session;

beforeEach(function () {
    $this->storage = new KeycloakTokenStorage;
});

function createTokenResult(
    string $accessToken = 'access-token-123',
    string $refreshToken = 'refresh-token-456',
    string $idToken = 'id-token-789',
    int $expiresIn = 300,
    int $refreshExpiresIn = 1800,
): TokenResult {
    return new TokenResult(
        accessToken: $accessToken,
        refreshToken: $refreshToken,
        idToken: $idToken,
        expiresIn: $expiresIn,
        refreshExpiresIn: $refreshExpiresIn,
    );
}

describe('storeTokens', function () {
    it('menyimpan access token ke session', function () {
        $tokens = createTokenResult();

        $this->storage->storeTokens($tokens);

        expect(Session::get('keycloak.tokens.access_token'))
            ->toBe('access-token-123');
    });

    it('mengenkripsi refresh token sebelum disimpan', function () {
        $tokens = createTokenResult();

        $this->storage->storeTokens($tokens);

        $storedRefreshToken = Session::get('keycloak.tokens.refresh_token');

        // Refresh token yang tersimpan harus terenkripsi (berbeda dari asli)
        expect($storedRefreshToken)->not->toBe('refresh-token-456');

        // Harus bisa didekripsi kembali ke nilai asli
        expect(Crypt::decryptString($storedRefreshToken))
            ->toBe('refresh-token-456');
    });

    it('menyimpan expires_at sebagai ISO8601 timestamp', function () {
        Carbon::setTestNow('2024-06-15 10:00:00');

        $tokens = createTokenResult(expiresIn: 300);

        $this->storage->storeTokens($tokens);

        $expiresAt = Session::get('keycloak.tokens.expires_at');

        expect(Carbon::parse($expiresAt)->toDateTimeString())
            ->toBe('2024-06-15 10:05:00');

        Carbon::setTestNow();
    });
});

describe('getAccessToken', function () {
    it('mengembalikan access token yang tersimpan', function () {
        $this->storage->storeTokens(createTokenResult());

        expect($this->storage->getAccessToken())
            ->toBe('access-token-123');
    });

    it('mengembalikan null jika tidak ada token tersimpan', function () {
        expect($this->storage->getAccessToken())->toBeNull();
    });
});

describe('getRefreshToken', function () {
    it('mengembalikan refresh token yang sudah didekripsi', function () {
        $this->storage->storeTokens(createTokenResult());

        expect($this->storage->getRefreshToken())
            ->toBe('refresh-token-456');
    });

    it('mengembalikan null jika tidak ada refresh token tersimpan', function () {
        expect($this->storage->getRefreshToken())->toBeNull();
    });
});

describe('getAccessTokenExpiry', function () {
    it('mengembalikan Carbon instance dari expires_at', function () {
        Carbon::setTestNow('2024-06-15 10:00:00');

        $this->storage->storeTokens(createTokenResult(expiresIn: 600));

        $expiry = $this->storage->getAccessTokenExpiry();

        expect($expiry)->toBeInstanceOf(CarbonInterface::class);
        expect($expiry->toDateTimeString())->toBe('2024-06-15 10:10:00');

        Carbon::setTestNow();
    });

    it('mengembalikan null jika tidak ada token tersimpan', function () {
        expect($this->storage->getAccessTokenExpiry())->toBeNull();
    });
});

describe('rotateTokens', function () {
    it('mengganti seluruh token set secara atomic', function () {
        // Simpan token awal
        $this->storage->storeTokens(createTokenResult());

        // Rotate dengan token baru
        $newTokens = createTokenResult(
            accessToken: 'new-access-token',
            refreshToken: 'new-refresh-token',
            idToken: 'new-id-token',
            expiresIn: 600,
        );

        $this->storage->rotateTokens($newTokens);

        expect($this->storage->getAccessToken())->toBe('new-access-token');
        expect($this->storage->getRefreshToken())->toBe('new-refresh-token');
    });

    it('tidak menyisakan data token lama setelah rotasi', function () {
        $this->storage->storeTokens(createTokenResult(
            accessToken: 'old-access',
            refreshToken: 'old-refresh',
        ));

        $this->storage->rotateTokens(createTokenResult(
            accessToken: 'new-access',
            refreshToken: 'new-refresh',
        ));

        // Pastikan tidak ada sisa data lama
        expect($this->storage->getAccessToken())->toBe('new-access');
        expect($this->storage->getRefreshToken())->toBe('new-refresh');
    });
});

describe('clearTokens', function () {
    it('menghapus seluruh data Keycloak dari session', function () {
        $this->storage->storeTokens(createTokenResult());

        // Simulasikan data session tambahan
        Session::put('keycloak.user', ['sub' => 'uuid', 'nip' => '198501152010011001']);
        Session::put('keycloak.permissions', ['cuti.view']);
        Session::put('keycloak.roles', ['operator']);
        Session::put('keycloak.oauth_state', ['state' => 'abc']);

        $this->storage->clearTokens();

        expect(Session::get('keycloak.tokens'))->toBeNull();
        expect(Session::get('keycloak.user'))->toBeNull();
        expect(Session::get('keycloak.permissions'))->toBeNull();
        expect(Session::get('keycloak.roles'))->toBeNull();
        expect(Session::get('keycloak.oauth_state'))->toBeNull();
    });
});

describe('isTokenValid', function () {
    it('mengembalikan true jika token ada dan belum expired', function () {
        Carbon::setTestNow('2024-06-15 10:00:00');

        $this->storage->storeTokens(createTokenResult(expiresIn: 300));

        expect($this->storage->isTokenValid())->toBeTrue();

        Carbon::setTestNow();
    });

    it('mengembalikan false jika token sudah expired', function () {
        Carbon::setTestNow('2024-06-15 10:00:00');

        $this->storage->storeTokens(createTokenResult(expiresIn: 300));

        // Pindah waktu ke setelah expiry
        Carbon::setTestNow('2024-06-15 10:06:00');

        expect($this->storage->isTokenValid())->toBeFalse();

        Carbon::setTestNow();
    });

    it('mengembalikan false jika tidak ada token tersimpan', function () {
        expect($this->storage->isTokenValid())->toBeFalse();
    });
});
