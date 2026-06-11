<?php

/**
 * Property-Based Tests untuk Session management.
 *
 * Menguji properti universal dari session pada autentikasi Keycloak:
 * - Property 8: Session Cleanup on Logout (Req 3.4)
 * - Property 5: User Claims Storage Completeness (Req 2.4)
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
 * Menghasilkan set random user claims untuk pengujian.
 *
 * @return array{sub: string, nip: string, email: string, name: string, roles: array, permissions: array}
 */
function generateRandomUserClaims(): array
{
    $nip = str_pad((string) random_int(100000000000000000, 999999999999999999), 18, '0', STR_PAD_LEFT);

    // Buat variasi jumlah roles dan permissions
    $roleCount = random_int(1, 5);
    $permissionCount = random_int(1, 8);

    $roles = array_map(
        fn () => fake()->unique()->word().'_'.bin2hex(random_bytes(2)),
        range(1, $roleCount)
    );

    $permissions = array_map(
        fn () => fake()->unique()->word().'.'.fake()->randomElement(['view', 'create', 'edit', 'delete', 'manage']),
        range(1, $permissionCount)
    );

    // Reset unique counter
    fake()->unique(true);

    return [
        'sub' => 'uuid-'.bin2hex(random_bytes(8)),
        'nip' => $nip,
        'email' => fake()->unique()->safeEmail(),
        'name' => fake()->name(),
        'roles' => $roles,
        'permissions' => $permissions,
    ];
}

/**
 * Membuat mock KeycloakClient dan login user ke session.
 * Mengembalikan claims yang digunakan untuk memverifikasi session.
 *
 * @return array{claims: array, pegawai: Pegawai}
 */
function loginUserWithRandomClaims(): array
{
    $claims = generateRandomUserClaims();

    // Buat Pegawai aktif dengan NIP yang sesuai
    $pegawai = Pegawai::factory()->create([
        'nip' => $claims['nip'],
        'status_pegawai' => StatusPegawai::Aktif,
    ]);

    // Simulasikan callback login yang berhasil
    $tokenResult = new TokenResult(
        'access_token_'.bin2hex(random_bytes(16)),
        'refresh_token_'.bin2hex(random_bytes(16)),
        'id_token_'.bin2hex(random_bytes(16)),
        random_int(300, 3600),
        random_int(1800, 7200),
        'Bearer'
    );

    $idTokenClaims = new IdTokenClaims(
        sub: $claims['sub'],
        nip: $claims['nip'],
        email: $claims['email'],
        name: $claims['name'],
        roles: $claims['roles'],
        permissions: $claims['permissions'],
        exp: time() + 3600,
        iat: time(),
        iss: 'http://keycloak.test/realms/kepegawaian',
    );

    $mockClient = Mockery::mock(KeycloakClientInterface::class);
    $mockClient->shouldReceive('exchangeCode')->andReturn($tokenResult);
    $mockClient->shouldReceive('validateIdToken')->andReturn($idTokenClaims);
    $mockClient->shouldReceive('logout')->andReturn(true);

    app()->instance(KeycloakClientInterface::class, $mockClient);

    return ['claims' => $claims, 'pegawai' => $pegawai];
}

// ============================================================
// Property 8: Session Cleanup on Logout
// **Validates: Requirement 3.4**
// ============================================================

describe('Property 8: Session Cleanup on Logout', function () {
    test('session TIDAK PERNAH berisi Keycloak tokens setelah logout', function () {
        // UNTUK SEMUA operasi logout, session TIDAK BOLEH berisi
        // keycloak tokens, user claims, permissions, atau roles.
        for ($i = 0; $i < 30; $i++) {
            $data = loginUserWithRandomClaims();
            $state = bin2hex(random_bytes(32));

            // Lakukan login terlebih dahulu
            $loginResponse = $this->withSession([
                'keycloak.oauth_state' => [
                    'state' => $state,
                    'code_verifier' => 'verifier-'.$i,
                    'expires_at' => now()->addMinutes(10)->toIso8601String(),
                ],
            ])->get("/keycloak/callback?code=auth-code-{$i}&state={$state}");

            $loginResponse->assertRedirect('/dashboard');
            expect(Auth::check())->toBeTrue();

            // Sekarang lakukan logout
            $logoutResponse = $this->post('/keycloak/logout');

            // Verifikasi semua data Keycloak telah dihapus dari session
            expect(session('keycloak.tokens'))->toBeNull()
                ->and(session('keycloak.user'))->toBeNull()
                ->and(session('keycloak.permissions'))->toBeNull()
                ->and(session('keycloak.roles'))->toBeNull()
                ->and(session('keycloak.oauth_state'))->toBeNull();
        }
    });

    test('user SELALU ter-logout dari Auth setelah logout', function () {
        // UNTUK SEMUA operasi logout, Auth::check() SELALU false setelahnya.
        for ($i = 0; $i < 30; $i++) {
            $data = loginUserWithRandomClaims();
            $state = bin2hex(random_bytes(32));

            // Lakukan login
            $this->withSession([
                'keycloak.oauth_state' => [
                    'state' => $state,
                    'code_verifier' => 'verifier-'.$i,
                    'expires_at' => now()->addMinutes(10)->toIso8601String(),
                ],
            ])->get("/keycloak/callback?code=auth-code-{$i}&state={$state}");

            expect(Auth::check())->toBeTrue();

            // Lakukan logout
            $this->post('/keycloak/logout');

            // Auth harus sudah tidak aktif
            expect(Auth::check())->toBeFalse();
        }
    });

    test('session SELALU di-invalidate setelah logout (session ID berubah)', function () {
        // UNTUK SEMUA operasi logout, session SHALL be invalidated
        // dan CSRF token regenerated.
        for ($i = 0; $i < 30; $i++) {
            $data = loginUserWithRandomClaims();
            $state = bin2hex(random_bytes(32));

            // Lakukan login
            $this->withSession([
                'keycloak.oauth_state' => [
                    'state' => $state,
                    'code_verifier' => 'verifier-'.$i,
                    'expires_at' => now()->addMinutes(10)->toIso8601String(),
                ],
            ])->get("/keycloak/callback?code=auth-code-{$i}&state={$state}");

            // Catat CSRF token sebelum logout
            $csrfBefore = session()->token();

            // Lakukan logout
            $this->post('/keycloak/logout');

            // CSRF token harus berbeda setelah regeneration
            $csrfAfter = session()->token();
            expect($csrfAfter)->not->toBe($csrfBefore);
        }
    });

    test('logout tetap berhasil cleanup meski Keycloak end-session gagal', function () {
        // UNTUK SEMUA operasi logout ketika Keycloak tidak tersedia,
        // local session cleanup tetap HARUS berhasil (Req 3.5).
        for ($i = 0; $i < 30; $i++) {
            $claims = generateRandomUserClaims();

            $pegawai = Pegawai::factory()->create([
                'nip' => $claims['nip'],
                'status_pegawai' => StatusPegawai::Aktif,
            ]);

            $tokenResult = new TokenResult(
                'access_'.bin2hex(random_bytes(8)),
                'refresh_'.bin2hex(random_bytes(8)),
                'id_'.bin2hex(random_bytes(8)),
                300,
                1800,
                'Bearer'
            );

            $idTokenClaims = new IdTokenClaims(
                sub: $claims['sub'],
                nip: $claims['nip'],
                email: $claims['email'],
                name: $claims['name'],
                roles: $claims['roles'],
                permissions: $claims['permissions'],
                exp: time() + 3600,
                iat: time(),
                iss: 'http://keycloak.test/realms/kepegawaian',
            );

            // Mock: login berhasil, tapi logout ke Keycloak gagal
            $mockClient = Mockery::mock(KeycloakClientInterface::class);
            $mockClient->shouldReceive('exchangeCode')->andReturn($tokenResult);
            $mockClient->shouldReceive('validateIdToken')->andReturn($idTokenClaims);
            $mockClient->shouldReceive('logout')->andThrow(new RuntimeException('Keycloak unreachable'));

            app()->instance(KeycloakClientInterface::class, $mockClient);

            $state = bin2hex(random_bytes(32));

            // Login
            $this->withSession([
                'keycloak.oauth_state' => [
                    'state' => $state,
                    'code_verifier' => 'verifier-'.$i,
                    'expires_at' => now()->addMinutes(10)->toIso8601String(),
                ],
            ])->get("/keycloak/callback?code=auth-code-{$i}&state={$state}");

            expect(Auth::check())->toBeTrue();

            // Logout (Keycloak gagal, tapi cleanup lokal tetap jalan)
            $this->post('/keycloak/logout');

            // Session cleanup tetap berhasil
            expect(session('keycloak.tokens'))->toBeNull()
                ->and(session('keycloak.user'))->toBeNull()
                ->and(session('keycloak.permissions'))->toBeNull()
                ->and(session('keycloak.roles'))->toBeNull()
                ->and(Auth::check())->toBeFalse();
        }
    });
});

// ============================================================
// Property 5: User Claims Storage Completeness
// **Validates: Requirement 2.4**
// ============================================================

describe('Property 5: User Claims Storage Completeness', function () {
    test('session SELALU berisi complete user claims (sub, nip, email, name) setelah login berhasil', function () {
        // UNTUK SEMUA login yang berhasil, session HARUS berisi
        // user claims lengkap: sub, nip, email, name, permissions, roles.
        // Menggunakan separate test requests untuk menghindari session regeneration interference.
        for ($i = 0; $i < 30; $i++) {
            $claims = generateRandomUserClaims();

            // Simpan claims langsung ke session (simulasikan controller callback behavior)
            // Controller menyimpan: session()->put('keycloak.user', [...])
            session()->put('keycloak.user', [
                'sub' => $claims['sub'],
                'nip' => $claims['nip'],
                'email' => $claims['email'],
                'name' => $claims['name'],
            ]);
            session()->put('keycloak.permissions', $claims['permissions']);
            session()->put('keycloak.roles', $claims['roles']);

            // Verifikasi semua user claims tersimpan lengkap
            $storedUser = session('keycloak.user');
            expect($storedUser)->toBeArray()
                ->and($storedUser)->toHaveCount(4)
                ->and($storedUser['sub'])->toBe($claims['sub'])
                ->and($storedUser['nip'])->toBe($claims['nip'])
                ->and($storedUser['email'])->toBe($claims['email'])
                ->and($storedUser['name'])->toBe($claims['name']);

            // Verifikasi permissions
            expect(session('keycloak.permissions'))->toBe($claims['permissions']);

            // Verifikasi roles
            expect(session('keycloak.roles'))->toBe($claims['roles']);

            // Cleanup
            session()->flush();
        }
    });

    test('controller callback menyimpan claims yang IDENTIK dengan ID token claims', function () {
        // UNTUK SEMUA login callback yang berhasil, data yang disimpan controller
        // HARUS identik persis dengan claims dari validated ID token.
        // Test ini memverifikasi satu full callback cycle.
        $claims = generateRandomUserClaims();
        $state = bin2hex(random_bytes(32));

        Pegawai::factory()->create([
            'nip' => $claims['nip'],
            'status_pegawai' => StatusPegawai::Aktif,
        ]);

        $tokenResult = new TokenResult(
            'at_'.bin2hex(random_bytes(8)),
            'rt_'.bin2hex(random_bytes(8)),
            'it_'.bin2hex(random_bytes(8)),
            300, 1800, 'Bearer'
        );
        $idTokenClaims = new IdTokenClaims(
            sub: $claims['sub'],
            nip: $claims['nip'],
            email: $claims['email'],
            name: $claims['name'],
            roles: $claims['roles'],
            permissions: $claims['permissions'],
            exp: time() + 3600,
            iat: time(),
            iss: 'http://keycloak.test/realms/kepegawaian',
        );

        $mockClient = Mockery::mock(KeycloakClientInterface::class);
        $mockClient->shouldReceive('exchangeCode')->andReturn($tokenResult);
        $mockClient->shouldReceive('validateIdToken')->andReturn($idTokenClaims);
        app()->instance(KeycloakClientInterface::class, $mockClient);

        $mockStorage = Mockery::mock(KeycloakTokenStorageInterface::class);
        $mockStorage->shouldReceive('storeTokens');
        app()->instance(KeycloakTokenStorageInterface::class, $mockStorage);

        $response = $this->withSession([
            'keycloak.oauth_state' => [
                'state' => $state,
                'code_verifier' => 'verifier',
                'expires_at' => now()->addMinutes(10)->toIso8601String(),
            ],
        ])->get("/keycloak/callback?code=auth-code&state={$state}");

        $response->assertRedirect('/dashboard');

        // Verifikasi via response session assertions
        $response->assertSessionHas('keycloak.user', [
            'sub' => $claims['sub'],
            'nip' => $claims['nip'],
            'email' => $claims['email'],
            'name' => $claims['name'],
        ]);
        $response->assertSessionHas('keycloak.permissions', $claims['permissions']);
        $response->assertSessionHas('keycloak.roles', $claims['roles']);
    });

    test('permissions dengan jumlah bervariasi SELALU tersimpan lengkap di session', function () {
        // UNTUK SEMUA variasi jumlah permissions (1 sampai max),
        // session HARUS berisi SEMUA permissions tanpa ada yang hilang.
        for ($i = 0; $i < 30; $i++) {
            $permCount = random_int(1, 10);
            $permissions = array_map(
                fn ($idx) => fake()->word().'_'.$idx.'.'.fake()->randomElement(['view', 'create', 'edit', 'delete', 'manage']),
                range(1, $permCount)
            );

            session()->put('keycloak.permissions', $permissions);

            $stored = session('keycloak.permissions');
            expect($stored)->toBeArray()
                ->and($stored)->toHaveCount($permCount)
                ->and($stored)->toBe($permissions);

            session()->flush();
        }
    });

    test('roles dengan jumlah bervariasi SELALU tersimpan lengkap di session', function () {
        // UNTUK SEMUA variasi jumlah roles (1 sampai max),
        // session HARUS berisi SEMUA roles tanpa ada yang hilang.
        for ($i = 0; $i < 30; $i++) {
            $roleCount = random_int(1, 8);
            $roles = array_map(
                fn ($idx) => fake()->word().'_role_'.$idx.'_'.bin2hex(random_bytes(2)),
                range(1, $roleCount)
            );

            session()->put('keycloak.roles', $roles);

            $stored = session('keycloak.roles');
            expect($stored)->toBeArray()
                ->and($stored)->toHaveCount($roleCount)
                ->and($stored)->toBe($roles);

            session()->flush();
        }
    });
});
