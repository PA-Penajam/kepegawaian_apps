<?php

/**
 * Property-Based Tests untuk KeycloakTokenStorage.
 *
 * Menguji properti universal dari token storage:
 * - Property 7: Token Encryption at Rest (Req 3.2)
 * - Property 10: Token Rotation Consistency (Req 4.2, 4.3)
 */

use App\Keycloak\DataTransferObjects\TokenResult;
use App\Keycloak\Services\KeycloakTokenStorage;
use Carbon\Carbon;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Session;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->storage = new KeycloakTokenStorage;
});

/**
 * Menghasilkan string token acak untuk property testing.
 */
function generateRandomToken(int $minLength = 20, int $maxLength = 200): string
{
    $length = random_int($minLength, $maxLength);

    return Str::random($length);
}

/**
 * Membuat TokenResult dengan token acak.
 */
function createRandomTokenResult(): TokenResult
{
    return new TokenResult(
        accessToken: generateRandomToken(),
        refreshToken: generateRandomToken(),
        idToken: generateRandomToken(),
        expiresIn: random_int(60, 3600),
        refreshExpiresIn: random_int(1800, 86400),
    );
}

// ============================================================
// Property 7: Token Encryption at Rest
// **Validates: Requirement 3.2**
// ============================================================

describe('Property 7: Token Encryption at Rest', function () {
    test('refresh token mentah TIDAK PERNAH muncul tanpa enkripsi di session data', function () {
        // Untuk semua refresh token yang disimpan via KeycloakTokenStorage,
        // nilai mentah refresh token tidak boleh muncul di session data.
        for ($i = 0; $i < 50; $i++) {
            Session::flush();
            $tokens = createRandomTokenResult();
            $rawRefreshToken = $tokens->refreshToken;

            $this->storage->storeTokens($tokens);

            // Ambil data session mentah
            $storedData = Session::get('keycloak.tokens');

            // Nilai mentah refresh token TIDAK BOLEH ada di session
            expect($storedData['refresh_token'])->not->toBe($rawRefreshToken);

            // Pastikan refresh token di session bukan substring dari raw token
            expect(str_contains($storedData['refresh_token'], $rawRefreshToken))->toBeFalse();
        }
    });

    test('nilai tersimpan selalu berbeda dari nilai asli (terenkripsi)', function () {
        // Untuk semua refresh token, nilai yang tersimpan harus selalu
        // berbeda dari token asli karena sudah dienkripsi.
        for ($i = 0; $i < 50; $i++) {
            Session::flush();
            $tokens = createRandomTokenResult();

            $this->storage->storeTokens($tokens);

            $storedRefreshToken = Session::get('keycloak.tokens.refresh_token');

            // Nilai terenkripsi HARUS berbeda dari nilai asli
            expect($storedRefreshToken)->not->toBe($tokens->refreshToken);
        }
    });

    test('dekripsi nilai tersimpan selalu mengembalikan refresh token asli', function () {
        // Untuk semua refresh token, mendekripsi nilai yang tersimpan
        // harus selalu mengembalikan token asli tanpa kehilangan data.
        for ($i = 0; $i < 50; $i++) {
            Session::flush();
            $tokens = createRandomTokenResult();

            $this->storage->storeTokens($tokens);

            $storedRefreshToken = Session::get('keycloak.tokens.refresh_token');

            // Dekripsi harus mengembalikan nilai asli
            $decrypted = Crypt::decryptString($storedRefreshToken);
            expect($decrypted)->toBe($tokens->refreshToken);
        }
    });

    test('penyimpanan token berbeda menghasilkan nilai terenkripsi berbeda', function () {
        // Untuk semua pasangan token yang berbeda, nilai enkripsi
        // yang dihasilkan harus selalu berbeda satu sama lain.
        $encryptedValues = [];

        for ($i = 0; $i < 50; $i++) {
            Session::flush();
            $tokens = createRandomTokenResult();

            $this->storage->storeTokens($tokens);

            $storedRefreshToken = Session::get('keycloak.tokens.refresh_token');
            $encryptedValues[] = $storedRefreshToken;
        }

        // Semua nilai terenkripsi harus unik
        $uniqueValues = array_unique($encryptedValues);
        expect(count($uniqueValues))->toBe(count($encryptedValues));
    });
});

// ============================================================
// Property 10: Token Rotation Consistency
// **Validates: Requirements 4.2, 4.3**
// ============================================================

describe('Property 10: Token Rotation Consistency', function () {
    test('setelah rotasi, getAccessToken() selalu mengembalikan access token BARU', function () {
        // Untuk semua rotasi token, getAccessToken() harus selalu
        // mengembalikan token baru, bukan token lama.
        for ($i = 0; $i < 50; $i++) {
            Session::flush();
            $oldTokens = createRandomTokenResult();
            $newTokens = createRandomTokenResult();

            $this->storage->storeTokens($oldTokens);
            $this->storage->rotateTokens($newTokens);

            expect($this->storage->getAccessToken())->toBe($newTokens->accessToken);
            expect($this->storage->getAccessToken())->not->toBe($oldTokens->accessToken);
        }
    });

    test('setelah rotasi, getRefreshToken() selalu mengembalikan refresh token BARU (terdekripsi)', function () {
        // Untuk semua rotasi token, getRefreshToken() harus selalu
        // mengembalikan refresh token baru yang sudah didekripsi.
        for ($i = 0; $i < 50; $i++) {
            Session::flush();
            $oldTokens = createRandomTokenResult();
            $newTokens = createRandomTokenResult();

            $this->storage->storeTokens($oldTokens);
            $this->storage->rotateTokens($newTokens);

            expect($this->storage->getRefreshToken())->toBe($newTokens->refreshToken);
            expect($this->storage->getRefreshToken())->not->toBe($oldTokens->refreshToken);
        }
    });

    test('setelah rotasi, tidak ada data dari token LAMA yang tetap tersedia', function () {
        // Untuk semua rotasi token, data token lama harus sepenuhnya
        // tergantikan oleh data token baru.
        for ($i = 0; $i < 50; $i++) {
            Session::flush();
            $oldTokens = createRandomTokenResult();
            $newTokens = createRandomTokenResult();

            $this->storage->storeTokens($oldTokens);
            $this->storage->rotateTokens($newTokens);

            $sessionData = Session::get('keycloak.tokens');

            // Access token lama tidak boleh ada
            expect($sessionData['access_token'])->not->toBe($oldTokens->accessToken);

            // Refresh token lama (terenkripsi) tidak boleh bisa didekripsi ke nilai lama
            $decryptedRefresh = Crypt::decryptString($sessionData['refresh_token']);
            expect($decryptedRefresh)->not->toBe($oldTokens->refreshToken);

            // Pastikan semua data merujuk ke token baru
            expect($sessionData['access_token'])->toBe($newTokens->accessToken);
            expect($decryptedRefresh)->toBe($newTokens->refreshToken);
        }
    });

    test('getAccessTokenExpiry() mencerminkan expiry token baru setelah rotasi', function () {
        // Untuk semua rotasi token, expiry harus selalu
        // mencerminkan expiresIn dari token baru.
        for ($i = 0; $i < 50; $i++) {
            Session::flush();
            Carbon::setTestNow('2024-06-15 10:00:00');

            $oldTokens = new TokenResult(
                accessToken: generateRandomToken(),
                refreshToken: generateRandomToken(),
                idToken: generateRandomToken(),
                expiresIn: random_int(60, 300),
                refreshExpiresIn: 1800,
            );

            $newExpiresIn = random_int(300, 3600);
            $newTokens = new TokenResult(
                accessToken: generateRandomToken(),
                refreshToken: generateRandomToken(),
                idToken: generateRandomToken(),
                expiresIn: $newExpiresIn,
                refreshExpiresIn: 1800,
            );

            $this->storage->storeTokens($oldTokens);
            $this->storage->rotateTokens($newTokens);

            $expiry = $this->storage->getAccessTokenExpiry();
            $expectedExpiry = Carbon::parse('2024-06-15 10:00:00')->addSeconds($newExpiresIn);

            expect($expiry->toDateTimeString())->toBe($expectedExpiry->toDateTimeString());

            Carbon::setTestNow();
        }
    });

    test('rotasi bersifat atomic — semua token diperbarui atau tidak ada', function () {
        // Untuk semua rotasi token, operasi harus bersifat atomic:
        // setelah rotateTokens(), semua field harus konsisten merujuk
        // ke token set yang sama (baru).
        for ($i = 0; $i < 50; $i++) {
            Session::flush();
            Carbon::setTestNow('2024-06-15 10:00:00');

            $oldTokens = createRandomTokenResult();
            $newTokens = createRandomTokenResult();

            $this->storage->storeTokens($oldTokens);
            $this->storage->rotateTokens($newTokens);

            // Verifikasi konsistensi: semua data harus dari token set yang sama
            $accessToken = $this->storage->getAccessToken();
            $refreshToken = $this->storage->getRefreshToken();
            $expiry = $this->storage->getAccessTokenExpiry();

            // Access token harus dari set baru
            expect($accessToken)->toBe($newTokens->accessToken);

            // Refresh token harus dari set baru
            expect($refreshToken)->toBe($newTokens->refreshToken);

            // Expiry harus sesuai expiresIn dari set baru
            $expectedExpiry = Carbon::parse('2024-06-15 10:00:00')->addSeconds($newTokens->expiresIn);
            expect($expiry->toDateTimeString())->toBe($expectedExpiry->toDateTimeString());

            Carbon::setTestNow();
        }
    });
});
