<?php

/**
 * Property-Based Tests untuk KeycloakAuthController.
 *
 * Menguji properti universal dari auth controller logic:
 * - Property 2: State CSRF Protection (Req 1.4, 1.5)
 * - Property 4: NIP Verification Gate (Req 2.2, 2.3)
 * - Property 6: Session Regeneration on Login (Req 3.1)
 */

use App\Enums\StatusPegawai;
use App\Keycloak\Contracts\KeycloakClientInterface;
use App\Keycloak\Contracts\KeycloakTokenStorageInterface;
use App\Keycloak\DataTransferObjects\IdTokenClaims;
use App\Keycloak\DataTransferObjects\TokenResult;
use App\Models\Pegawai;
use Illuminate\Support\Facades\Auth;

// ============================================================
// Helper Functions untuk Property Testing
// ============================================================

/**
 * Menghasilkan state string acak yang berbeda dari expected state.
 */
function generateMismatchedState(string $validState): string
{
    $strategies = [
        // State acak dengan panjang berbeda
        fn () => bin2hex(random_bytes(random_int(1, 64))),
        // State kosong
        fn () => '',
        // State yang sedikit berbeda (1 karakter diubah)
        fn () => substr_replace($validState, 'x', random_int(0, max(0, strlen($validState) - 1)), 1),
        // State dengan karakter non-hex
        fn () => str_repeat('z', 64),
        // State yang terlalu pendek
        fn () => bin2hex(random_bytes(4)),
        // State yang terlalu panjang
        fn () => bin2hex(random_bytes(128)),
    ];

    return $strategies[array_rand($strategies)]();
}

/**
 * Menghasilkan NIP acak yang TIDAK valid (bukan 18 digit angka).
 */
function generateInvalidNip(): string
{
    $strategies = [
        // Terlalu pendek (1-17 digit)
        fn () => str_pad((string) random_int(1, 999999999), random_int(1, 17), '0', STR_PAD_LEFT),
        // Terlalu panjang (19+ digit)
        fn () => str_pad((string) random_int(1, 999999999), random_int(19, 25), '0', STR_PAD_LEFT),
        // Mengandung huruf
        fn () => substr(str_shuffle('abcdefghij1234567890'), 0, 18),
        // Mengandung karakter spesial
        fn () => '12345678901234567!',
        // Kosong
        fn () => '',
        // Spasi
        fn () => '   ',
        // Campuran huruf dan angka
        fn () => 'NIP'.str_pad((string) random_int(1, 999), 15, '0', STR_PAD_LEFT),
    ];

    return $strategies[array_rand($strategies)]();
}

/**
 * Menghasilkan NIP valid 18 digit angka secara acak.
 */
function generateValidNip(): string
{
    // Format NIP ASN: 18 digit angka
    return str_pad((string) random_int(100000000000000000, 999999999999999999), 18, '0', STR_PAD_LEFT);
}

/**
 * Membuat mock KeycloakClient yang melewati validasi sampai tahap NIP.
 */
function createMockClientForNipTest(string $nip): KeycloakClientInterface
{
    $tokenResult = new TokenResult('at', 'rt', 'it', 300, 1800, 'Bearer');
    $claims = new IdTokenClaims(
        sub: 'uuid-'.bin2hex(random_bytes(4)),
        nip: $nip,
        email: 'test@email.com',
        name: 'Test User',
        roles: ['pegawai'],
        permissions: ['cuti.view'],
        exp: time() + 3600,
        iat: time(),
        iss: 'http://keycloak.test/realms/kepegawaian',
    );

    $mockClient = Mockery::mock(KeycloakClientInterface::class);
    $mockClient->shouldReceive('exchangeCode')->andReturn($tokenResult);
    $mockClient->shouldReceive('validateIdToken')->andReturn($claims);

    return $mockClient;
}

// ============================================================
// Property 2: State CSRF Protection
// **Validates: Requirements 1.4, 1.5**
// ============================================================

describe('Property 2: State CSRF Protection', function () {
    test('callback SELALU menolak state yang tidak cocok dengan 403', function () {
        // UNTUK SEMUA callback dengan state yang tidak cocok,
        // sistem SELALU menolak dengan abort 403 (Req 1.5).
        for ($i = 0; $i < 50; $i++) {
            $storedState = bin2hex(random_bytes(32));
            $mismatchedState = generateMismatchedState($storedState);

            // Pastikan state memang berbeda
            if ($mismatchedState === $storedState) {
                continue;
            }

            $response = $this->withSession([
                'keycloak.oauth_state' => [
                    'state' => $storedState,
                    'code_verifier' => 'verifier-'.$i,
                    'expires_at' => now()->addMinutes(10)->toIso8601String(),
                ],
            ])->get('/keycloak/callback?code=auth-code&state='.urlencode($mismatchedState));

            $response->assertStatus(403);
        }
    });

    test('oauth_state SELALU dihapus dari session setelah callback diproses', function () {
        // UNTUK SEMUA callback (baik valid maupun invalid state),
        // oauth_state harus dihapus dari session setelah diproses.
        for ($i = 0; $i < 50; $i++) {
            $storedState = bin2hex(random_bytes(32));
            $callbackState = bin2hex(random_bytes(32)); // state berbeda

            $this->withSession([
                'keycloak.oauth_state' => [
                    'state' => $storedState,
                    'code_verifier' => 'verifier-'.$i,
                    'expires_at' => now()->addMinutes(10)->toIso8601String(),
                ],
            ])->get('/keycloak/callback?code=abc&state='.urlencode($callbackState));

            // oauth_state harus sudah dihapus (session().pull())
            expect(session('keycloak.oauth_state'))->toBeNull();
        }
    });

    test('state kosong atau tidak ada parameter state SELALU ditolak dengan 403', function () {
        // UNTUK SEMUA callback tanpa state atau state kosong,
        // sistem SELALU menolak dengan abort 403 (Req 1.5).
        $emptyStates = ['', ' ', null];

        for ($i = 0; $i < 30; $i++) {
            $storedState = bin2hex(random_bytes(32));

            foreach ($emptyStates as $emptyState) {
                $url = '/keycloak/callback?code=auth-code';
                if ($emptyState !== null) {
                    $url .= '&state='.urlencode($emptyState);
                }

                $response = $this->withSession([
                    'keycloak.oauth_state' => [
                        'state' => $storedState,
                        'code_verifier' => 'verifier',
                        'expires_at' => now()->addMinutes(10)->toIso8601String(),
                    ],
                ])->get($url);

                $response->assertStatus(403);
            }
        }
    });

    test('callback tanpa session oauth_state SELALU ditolak dengan 403', function () {
        // UNTUK SEMUA callback ketika session tidak memiliki oauth_state,
        // sistem SELALU menolak dengan abort 403 (Req 1.5).
        for ($i = 0; $i < 50; $i++) {
            $randomState = bin2hex(random_bytes(32));

            $response = $this->get('/keycloak/callback?code=abc&state='.$randomState);

            $response->assertStatus(403);
        }
    });
});

// ============================================================
// Property 4: NIP Verification Gate
// **Validates: Requirements 2.2, 2.3**
// ============================================================

describe('Property 4: NIP Verification Gate', function () {
    test('NIP bukan 18 digit angka SELALU ditolak', function () {
        // UNTUK SEMUA NIP yang bukan 18 digit angka,
        // sistem SELALU menolak dengan error yang menunjukkan identitas tidak valid.
        for ($i = 0; $i < 50; $i++) {
            $invalidNip = generateInvalidNip();
            $state = bin2hex(random_bytes(32));

            $mockClient = createMockClientForNipTest($invalidNip);
            $this->app->instance(KeycloakClientInterface::class, $mockClient);

            $response = $this->withSession([
                'keycloak.oauth_state' => [
                    'state' => $state,
                    'code_verifier' => 'verifier',
                    'expires_at' => now()->addMinutes(10)->toIso8601String(),
                ],
            ])->get("/keycloak/callback?code=abc&state={$state}");

            $response->assertRedirect(route('keycloak.login'));
            $response->assertSessionHas('error', 'Token berisi informasi identitas yang tidak valid.');
        }
    });

    test('NIP 18 digit yang tidak terdaftar di Pegawai SELALU ditolak', function () {
        // UNTUK SEMUA NIP valid (18 digit) yang tidak ada di tabel Pegawai,
        // sistem SELALU menolak dengan error "NIP tidak terdaftar" (Req 2.4).
        for ($i = 0; $i < 50; $i++) {
            $validNip = generateValidNip();
            $state = bin2hex(random_bytes(32));

            $mockClient = createMockClientForNipTest($validNip);
            $this->app->instance(KeycloakClientInterface::class, $mockClient);

            $response = $this->withSession([
                'keycloak.oauth_state' => [
                    'state' => $state,
                    'code_verifier' => 'verifier',
                    'expires_at' => now()->addMinutes(10)->toIso8601String(),
                ],
            ])->get("/keycloak/callback?code=abc&state={$state}");

            $response->assertRedirect(route('keycloak.login'));
            $response->assertSessionHas('error', 'NIP tidak terdaftar dalam sistem kepegawaian');
        }
    });

    test('NIP 18 digit dengan status Pegawai tidak aktif SELALU ditolak', function () {
        // UNTUK SEMUA NIP yang terdaftar tetapi memiliki status bukan Aktif,
        // sistem SELALU menolak dengan error yang menunjukkan akun tidak aktif.
        $inactiveStatuses = [
            StatusPegawai::Pensiun,
            StatusPegawai::MutasiKeluar,
            StatusPegawai::Meninggal,
            StatusPegawai::Diberhentikan,
        ];

        // Buat kumpulan Pegawai dengan status tidak aktif
        $testCases = [];
        for ($i = 0; $i < 40; $i++) {
            $validNip = generateValidNip();
            $status = $inactiveStatuses[array_rand($inactiveStatuses)];

            Pegawai::factory()->create([
                'nip' => $validNip,
                'status_pegawai' => $status,
            ]);

            $testCases[] = $validNip;
        }

        // Verifikasi setiap NIP ditolak
        foreach ($testCases as $validNip) {
            $state = bin2hex(random_bytes(32));

            $mockClient = createMockClientForNipTest($validNip);
            $this->app->instance(KeycloakClientInterface::class, $mockClient);

            $response = $this->withSession([
                'keycloak.oauth_state' => [
                    'state' => $state,
                    'code_verifier' => 'verifier',
                    'expires_at' => now()->addMinutes(10)->toIso8601String(),
                ],
            ])->get("/keycloak/callback?code=abc&state={$state}");

            $response->assertRedirect(route('keycloak.login'));
            $response->assertSessionHas('error', 'Akun Pegawai tidak aktif. Hubungi administrator untuk informasi lebih lanjut.');
        }
    });

    test('hanya NIP 18 digit angka yang terdaftar sebagai Pegawai aktif yang diterima', function () {
        // UNTUK SEMUA NIP valid yang terdaftar sebagai Pegawai aktif,
        // sistem SELALU menerima dan login berhasil.
        for ($i = 0; $i < 20; $i++) {
            $validNip = generateValidNip();
            $state = bin2hex(random_bytes(32));

            // Buat Pegawai aktif
            Pegawai::factory()->create([
                'nip' => $validNip,
                'status_pegawai' => StatusPegawai::Aktif,
            ]);

            $mockClient = createMockClientForNipTest($validNip);
            $this->app->instance(KeycloakClientInterface::class, $mockClient);

            $mockStorage = Mockery::mock(KeycloakTokenStorageInterface::class);
            $mockStorage->shouldReceive('storeTokens');
            $this->app->instance(KeycloakTokenStorageInterface::class, $mockStorage);

            $response = $this->withSession([
                'keycloak.oauth_state' => [
                    'state' => $state,
                    'code_verifier' => 'verifier',
                    'expires_at' => now()->addMinutes(10)->toIso8601String(),
                ],
            ])->get("/keycloak/callback?code=abc&state={$state}");

            // Harus redirect ke dashboard (bukan ke login)
            $response->assertRedirect('/dashboard');
            expect(Auth::check())->toBeTrue();

            // Logout untuk iterasi berikutnya
            Auth::logout();
        }
    });
});

// ============================================================
// Property 6: Session Regeneration on Login
// **Validates: Requirement 3.1**
// ============================================================

describe('Property 6: Session Regeneration on Login', function () {
    test('session ID SELALU berubah setelah autentikasi berhasil', function () {
        // UNTUK SEMUA login callback yang berhasil,
        // session ID harus SELALU berubah (mencegah session fixation).
        for ($i = 0; $i < 20; $i++) {
            $validNip = generateValidNip();
            $state = bin2hex(random_bytes(32));

            // Buat Pegawai aktif
            Pegawai::factory()->create([
                'nip' => $validNip,
                'status_pegawai' => StatusPegawai::Aktif,
            ]);

            $mockClient = createMockClientForNipTest($validNip);
            $this->app->instance(KeycloakClientInterface::class, $mockClient);

            $mockStorage = Mockery::mock(KeycloakTokenStorageInterface::class);
            $mockStorage->shouldReceive('storeTokens');
            $this->app->instance(KeycloakTokenStorageInterface::class, $mockStorage);

            // Catat session ID sebelum callback
            $sessionIdBefore = session()->getId();

            $response = $this->withSession([
                'keycloak.oauth_state' => [
                    'state' => $state,
                    'code_verifier' => 'verifier',
                    'expires_at' => now()->addMinutes(10)->toIso8601String(),
                ],
            ])->get("/keycloak/callback?code=abc&state={$state}");

            // Verifikasi login berhasil
            $response->assertRedirect('/dashboard');

            // Session ID harus berbeda setelah login berhasil
            $sessionIdAfter = session()->getId();
            expect($sessionIdAfter)->not->toBe($sessionIdBefore);

            // Logout untuk iterasi berikutnya
            Auth::logout();
            session()->flush();
        }
    });

    test('data session dipertahankan melalui regenerasi', function () {
        // UNTUK SEMUA login callback yang berhasil,
        // data keycloak.user, keycloak.permissions, dan keycloak.roles
        // harus tetap tersimpan setelah session regeneration.
        for ($i = 0; $i < 20; $i++) {
            $validNip = generateValidNip();
            $state = bin2hex(random_bytes(32));

            // Buat Pegawai aktif
            Pegawai::factory()->create([
                'nip' => $validNip,
                'status_pegawai' => StatusPegawai::Aktif,
            ]);

            $mockClient = createMockClientForNipTest($validNip);
            $this->app->instance(KeycloakClientInterface::class, $mockClient);

            $mockStorage = Mockery::mock(KeycloakTokenStorageInterface::class);
            $mockStorage->shouldReceive('storeTokens');
            $this->app->instance(KeycloakTokenStorageInterface::class, $mockStorage);

            $response = $this->withSession([
                'keycloak.oauth_state' => [
                    'state' => $state,
                    'code_verifier' => 'verifier',
                    'expires_at' => now()->addMinutes(10)->toIso8601String(),
                ],
            ])->get("/keycloak/callback?code=abc&state={$state}");

            // Verifikasi login berhasil
            $response->assertRedirect('/dashboard');

            // Verifikasi data session tersimpan setelah regeneration
            // Akses session store dari response untuk memastikan data tersedia
            $responseSession = app('session.store');
            $userData = $responseSession->get('keycloak.user');

            expect($userData)->toBeArray()
                ->and($userData)->toHaveKey('nip')
                ->and($userData)->toHaveKey('sub')
                ->and($userData)->toHaveKey('email')
                ->and($userData)->toHaveKey('name')
                ->and($responseSession->get('keycloak.permissions'))->toBeArray()
                ->and($responseSession->get('keycloak.roles'))->toBeArray();

            // Cleanup untuk iterasi berikutnya
            Auth::logout();
            session()->invalidate();
            session()->regenerateToken();
        }
    });
});
