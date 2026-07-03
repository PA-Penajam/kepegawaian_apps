<?php

/**
 * Integration Tests: Complete Auth Flow
 *
 * Test end-to-end flow autentikasi Keycloak termasuk:
 * - Login → Callback → Session Creation → Dashboard Access
 * - Token Refresh Cycle → Session Maintained
 * - Logout → Session Cleared → Keycloak Notified
 * - Emergency Bypass Flow saat Circuit Open
 *
 * Requirements: 1.1-1.9, 2.1-2.6, 3.1-3.5, 4.1-4.6
 */

use App\Enums\StatusPegawai;
use App\Keycloak\Contracts\CircuitBreakerInterface;
use App\Keycloak\Contracts\KeycloakClientInterface;
use App\Keycloak\Contracts\KeycloakTokenStorageInterface;
use App\Keycloak\DataTransferObjects\AuthorizationRequest;
use App\Keycloak\DataTransferObjects\IdTokenClaims;
use App\Keycloak\DataTransferObjects\PkcePair;
use App\Keycloak\DataTransferObjects\TokenResult;
use App\Keycloak\Exceptions\KeycloakCircuitOpenException;
use App\Keycloak\Exceptions\KeycloakException;
use App\Models\Pegawai;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Route;

// ============================================================================
// Flow 1: Login → Callback → Session Creation → Dashboard Access
// ============================================================================

describe('login → callback → session → dashboard (Req 1.1-1.9, 2.1-2.6, 3.1-3.5)', function () {
    test('complete auth flow: login redirect → callback → session → authenticated dashboard access', function () {
        // Arrange: Buat Pegawai aktif
        $pegawai = Pegawai::factory()->create([
            'nip' => '198501152010011001',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);

        $state = bin2hex(random_bytes(32));
        $codeVerifier = 'test-verifier-abcdef123456789';
        $codeChallenge = 'test-challenge-xyz';

        $authRequest = new AuthorizationRequest(
            url: 'http://keycloak.test/realms/kepegawaian/protocol/openid-connect/auth?client_id=test',
            state: $state,
            pkce: new PkcePair(
                verifier: $codeVerifier,
                challenge: $codeChallenge,
                method: 'S256',
            ),
        );

        $tokenResult = new TokenResult(
            accessToken: 'access-token-jwt-value',
            refreshToken: 'refresh-token-value',
            idToken: 'id-token-jwt-value',
            expiresIn: 300,
            refreshExpiresIn: 1800,
            tokenType: 'Bearer',
        );

        $claims = new IdTokenClaims(
            sub: 'kc-uuid-123-456',
            nip: '198501152010011001',
            email: 'pegawai@instansi.go.id',
            name: 'Budi Santoso',
            roles: ['pegawai', 'operator'],
            permissions: ['cuti.view', 'cuti.create', 'profil.view'],
            exp: time() + 3600,
            iat: time(),
            iss: 'http://keycloak.test/realms/kepegawaian',
        );

        $mockClient = Mockery::mock(KeycloakClientInterface::class);
        $mockClient->shouldReceive('buildAuthorizationUrl')->once()->andReturn($authRequest);
        $mockClient->shouldReceive('exchangeCode')
            ->once()
            ->with('auth-code-from-keycloak', $codeVerifier, Mockery::any())
            ->andReturn($tokenResult);
        $mockClient->shouldReceive('validateIdToken')
            ->once()
            ->with('id-token-jwt-value')
            ->andReturn($claims);

        $mockStorage = Mockery::mock(KeycloakTokenStorageInterface::class);
        $mockStorage->shouldReceive('storeTokens')->once()->with($tokenResult);

        $this->app->instance(KeycloakClientInterface::class, $mockClient);
        $this->app->instance(KeycloakTokenStorageInterface::class, $mockStorage);

        // Step 1: Initiate login — redirect ke Keycloak
        $loginResponse = $this->get('/keycloak/login');
        $loginResponse->assertRedirect($authRequest->url);

        // Verifikasi session berisi oauth_state
        $oauthState = session('keycloak.oauth_state');
        expect($oauthState)->not->toBeNull()
            ->and($oauthState['state'])->toBe($state)
            ->and($oauthState['code_verifier'])->toBe($codeVerifier);

        // Step 2: Callback dari Keycloak — exchange code + verify NIP
        $callbackResponse = $this->withSession([
            'keycloak.oauth_state' => [
                'state' => $state,
                'code_verifier' => $codeVerifier,
                'expires_at' => now()->addMinutes(10)->toIso8601String(),
            ],
        ])->get("/keycloak/callback?code=auth-code-from-keycloak&state={$state}");

        // Step 3: Verifikasi redirect ke dashboard
        $callbackResponse->assertRedirect('/dashboard');

        // Step 4: Verifikasi user terautentikasi
        expect(Auth::check())->toBeTrue()
            ->and(Auth::id())->toBe($pegawai->id);

        // Step 5: Verifikasi session berisi user claims (Req 2.6)
        expect(session('keycloak.user'))->toBe([
            'sub' => 'kc-uuid-123-456',
            'nip' => '198501152010011001',
            'email' => 'pegawai@instansi.go.id',
            'name' => 'Budi Santoso',
        ]);

        // Step 6: Verifikasi permissions dan roles di session
        expect(session('keycloak.permissions'))->toBe(['cuti.view', 'cuti.create', 'profil.view'])
            ->and(session('keycloak.roles'))->toBe(['pegawai', 'operator']);

        // Step 7: Verifikasi oauth_state dihapus (cegah replay — Req 1.4)
        expect(session('keycloak.oauth_state'))->toBeNull();
    });

    test('state mismatch → reject 403, clear state, no session created (Req 1.5)', function () {
        $response = $this->withSession([
            'keycloak.oauth_state' => [
                'state' => 'correct-state-value',
                'code_verifier' => 'verifier',
                'expires_at' => now()->addMinutes(10)->toIso8601String(),
            ],
        ])->get('/keycloak/callback?code=abc&state=wrong-state-value');

        $response->assertStatus(403);
        expect(Auth::check())->toBeFalse();
        expect(session('keycloak.user'))->toBeNull();
    });

    test('expired state → reject 403, redirect to login (Req 1.5)', function () {
        $expiredState = 'expired-state-123';

        $response = $this->withSession([
            'keycloak.oauth_state' => [
                'state' => $expiredState,
                'code_verifier' => 'verifier',
                'expires_at' => now()->subMinutes(1)->toIso8601String(),
            ],
        ])->get("/keycloak/callback?code=abc&state={$expiredState}");

        $response->assertStatus(403);
        expect(Auth::check())->toBeFalse();
    });

    test('NIP tidak terdaftar → reject, no session created (Req 2.4)', function () {
        $state = bin2hex(random_bytes(32));
        $tokenResult = new TokenResult('at', 'rt', 'it', 300, 1800, 'Bearer');
        $claims = new IdTokenClaims(
            sub: 'uuid-999',
            nip: '999999999999999999',
            email: 'unknown@test.com',
            name: 'Unknown Person',
            roles: [],
            permissions: [],
            exp: time() + 3600,
            iat: time(),
            iss: 'http://keycloak.test/realms/kepegawaian',
        );

        $mockClient = Mockery::mock(KeycloakClientInterface::class);
        $mockClient->shouldReceive('exchangeCode')->andReturn($tokenResult);
        $mockClient->shouldReceive('validateIdToken')->andReturn($claims);
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
        expect(Auth::check())->toBeFalse();
        expect(session('keycloak.user'))->toBeNull();
    });

    test('Pegawai tidak aktif → reject, no session created (Req 2.5)', function () {
        $state = bin2hex(random_bytes(32));
        Pegawai::factory()->create([
            'nip' => '198501152010011099',
            'status_pegawai' => StatusPegawai::Pensiun,
        ]);

        $tokenResult = new TokenResult('at', 'rt', 'it', 300, 1800, 'Bearer');
        $claims = new IdTokenClaims(
            sub: 'uuid-pensiun',
            nip: '198501152010011099',
            email: 'pensiun@test.com',
            name: 'Pensiun Person',
            roles: [],
            permissions: [],
            exp: time() + 3600,
            iat: time(),
            iss: 'http://keycloak.test/realms/kepegawaian',
        );

        $mockClient = Mockery::mock(KeycloakClientInterface::class);
        $mockClient->shouldReceive('exchangeCode')->andReturn($tokenResult);
        $mockClient->shouldReceive('validateIdToken')->andReturn($claims);
        $this->app->instance(KeycloakClientInterface::class, $mockClient);

        $response = $this->withSession([
            'keycloak.oauth_state' => [
                'state' => $state,
                'code_verifier' => 'verifier',
                'expires_at' => now()->addMinutes(10)->toIso8601String(),
            ],
        ])->get("/keycloak/callback?code=abc&state={$state}");

        $response->assertRedirect(route('keycloak.login'));
        expect(Auth::check())->toBeFalse();
    });

    test('token exchange gagal → redirect to login with error (Req 1.8)', function () {
        $state = bin2hex(random_bytes(32));

        $mockClient = Mockery::mock(KeycloakClientInterface::class);
        $mockClient->shouldReceive('exchangeCode')
            ->andThrow(new KeycloakException('Exchange failed', KeycloakException::CODE_EXCHANGE_FAILED));
        $this->app->instance(KeycloakClientInterface::class, $mockClient);

        $response = $this->withSession([
            'keycloak.oauth_state' => [
                'state' => $state,
                'code_verifier' => 'verifier',
                'expires_at' => now()->addMinutes(10)->toIso8601String(),
            ],
        ])->get("/keycloak/callback?code=invalid-code&state={$state}");

        $response->assertRedirect(route('keycloak.login'));
        $response->assertSessionHas('error');
        expect(Auth::check())->toBeFalse();
    });

    test('ID token validation gagal → discard tokens, redirect to login (Req 1.9)', function () {
        $state = bin2hex(random_bytes(32));
        $tokenResult = new TokenResult('at', 'rt', 'invalid-it', 300, 1800, 'Bearer');

        $mockClient = Mockery::mock(KeycloakClientInterface::class);
        $mockClient->shouldReceive('exchangeCode')->andReturn($tokenResult);
        $mockClient->shouldReceive('validateIdToken')
            ->andThrow(new KeycloakException('Invalid signature', KeycloakException::INVALID_TOKEN));
        $this->app->instance(KeycloakClientInterface::class, $mockClient);

        $response = $this->withSession([
            'keycloak.oauth_state' => [
                'state' => $state,
                'code_verifier' => 'verifier',
                'expires_at' => now()->addMinutes(10)->toIso8601String(),
            ],
        ])->get("/keycloak/callback?code=abc&state={$state}");

        $response->assertRedirect(route('keycloak.login'));
        expect(Auth::check())->toBeFalse();
        expect(session('keycloak.tokens'))->toBeNull();
    });
});

// ============================================================================
// Flow 2: Token Refresh Cycle → Session Maintained
// ============================================================================

describe('token refresh cycle → session maintained (Req 4.1-4.6)', function () {
    beforeEach(function () {
        // Daftarkan route test yang menggunakan keycloak middleware group
        Route::middleware(['web', 'keycloak.refresh'])->get('/_test/protected', function () {
            return response()->json(['status' => 'ok']);
        });
    });

    test('proactive refresh ketika token dalam 60s dari expiry → session terjaga', function () {
        $pegawai = Pegawai::factory()->create([
            'nip' => '198501152010011010',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);

        $newTokenResult = new TokenResult(
            accessToken: 'new-access-token',
            refreshToken: 'new-refresh-token',
            idToken: 'new-id-token',
            expiresIn: 300,
            refreshExpiresIn: 1800,
            tokenType: 'Bearer',
        );

        $mockClient = Mockery::mock(KeycloakClientInterface::class);
        $mockClient->shouldReceive('refreshToken')
            ->once()
            ->with('current-refresh-token')
            ->andReturn($newTokenResult);

        $mockStorage = Mockery::mock(KeycloakTokenStorageInterface::class);
        // Token akan expired dalam 30 detik (dalam threshold 60s)
        $mockStorage->shouldReceive('getAccessTokenExpiry')
            ->andReturn(now()->addSeconds(30));
        $mockStorage->shouldReceive('getRefreshToken')
            ->andReturn('current-refresh-token');
        $mockStorage->shouldReceive('rotateTokens')
            ->once()
            ->with($newTokenResult);
        $mockStorage->shouldReceive('isTokenValid')->andReturn(true);

        $mockCircuitBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $mockCircuitBreaker->shouldReceive('call')
            ->once()
            ->andReturnUsing(fn (callable $op) => $op());
        $mockCircuitBreaker->shouldReceive('isOpen')->andReturn(false);

        $this->app->instance(KeycloakClientInterface::class, $mockClient);
        $this->app->instance(KeycloakTokenStorageInterface::class, $mockStorage);
        $this->app->instance(CircuitBreakerInterface::class, $mockCircuitBreaker);

        // Simulasi authenticated request ke route dengan keycloak middleware
        $response = $this->actingAs($pegawai)
            ->withSession([
                'keycloak.tokens' => [
                    'access_token' => 'old-access-token',
                    'refresh_token' => 'encrypted-refresh',
                    'expires_at' => now()->addSeconds(30)->toIso8601String(),
                ],
                'keycloak.user' => [
                    'sub' => 'uuid-abc',
                    'nip' => '198501152010011010',
                    'email' => 'test@pegawai.go.id',
                    'name' => 'Test Pegawai',
                ],
                'keycloak.permissions' => ['cuti.view'],
                'keycloak.roles' => ['pegawai'],
            ])
            ->get('/_test/protected');

        // Request harus berhasil — session terjaga setelah refresh
        $response->assertStatus(200);
        expect(Auth::check())->toBeTrue();
    });

    test('refresh gagal (revoked) → session cleared, redirect to login (Req 4.3)', function () {
        $pegawai = Pegawai::factory()->create([
            'nip' => '198501152010011011',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);

        $mockClient = Mockery::mock(KeycloakClientInterface::class);
        $mockClient->shouldReceive('refreshToken')
            ->andThrow(new KeycloakException('Token revoked', KeycloakException::REFRESH_FAILED));

        $mockStorage = Mockery::mock(KeycloakTokenStorageInterface::class);
        $mockStorage->shouldReceive('getAccessTokenExpiry')
            ->andReturn(now()->addSeconds(30));
        $mockStorage->shouldReceive('getRefreshToken')
            ->andReturn('revoked-refresh-token');
        $mockStorage->shouldReceive('clearTokens')->once();

        $mockCircuitBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $mockCircuitBreaker->shouldReceive('call')
            ->andReturnUsing(fn (callable $op) => $op());
        $mockCircuitBreaker->shouldReceive('isOpen')->andReturn(false);

        $this->app->instance(KeycloakClientInterface::class, $mockClient);
        $this->app->instance(KeycloakTokenStorageInterface::class, $mockStorage);
        $this->app->instance(CircuitBreakerInterface::class, $mockCircuitBreaker);

        $response = $this->actingAs($pegawai)
            ->withSession([
                'keycloak.tokens' => [
                    'access_token' => 'old-token',
                    'refresh_token' => 'encrypted',
                    'expires_at' => now()->addSeconds(30)->toIso8601String(),
                ],
                'keycloak.user' => ['sub' => 'uuid', 'nip' => '198501152010011011', 'email' => 'e@e.id', 'name' => 'Test'],
                'keycloak.permissions' => [],
                'keycloak.roles' => [],
            ])
            ->get('/_test/protected');

        // Harus redirect ke login karena refresh gagal
        $response->assertRedirect(route('keycloak.login'));
    });

    test('Keycloak unavailable + token masih valid → lanjutkan degraded mode (Req 4.4)', function () {
        $pegawai = Pegawai::factory()->create([
            'nip' => '198501152010011012',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);

        $mockStorage = Mockery::mock(KeycloakTokenStorageInterface::class);
        $mockStorage->shouldReceive('getAccessTokenExpiry')
            ->andReturn(now()->addSeconds(30));
        $mockStorage->shouldReceive('getRefreshToken')
            ->andReturn('some-refresh-token');
        $mockStorage->shouldReceive('isTokenValid')->andReturn(true);

        $mockCircuitBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $mockCircuitBreaker->shouldReceive('call')
            ->andThrow(new KeycloakCircuitOpenException('Circuit is open'));
        $mockCircuitBreaker->shouldReceive('isOpen')->andReturn(true);

        $this->app->instance(KeycloakTokenStorageInterface::class, $mockStorage);
        $this->app->instance(CircuitBreakerInterface::class, $mockCircuitBreaker);

        $response = $this->actingAs($pegawai)
            ->withSession([
                'keycloak.tokens' => [
                    'access_token' => 'still-valid-token',
                    'refresh_token' => 'encrypted',
                    'expires_at' => now()->addSeconds(30)->toIso8601String(),
                ],
                'keycloak.user' => ['sub' => 'uuid', 'nip' => '198501152010011012', 'email' => 'e@e.id', 'name' => 'Test'],
                'keycloak.permissions' => ['cuti.view'],
                'keycloak.roles' => ['pegawai'],
            ])
            ->get('/_test/protected');

        // Request harus tetap berhasil dalam degraded mode
        $response->assertStatus(200);
        expect(Auth::check())->toBeTrue();
    });

    test('Keycloak unavailable + token expired → force logout (Req 4.5)', function () {
        $pegawai = Pegawai::factory()->create([
            'nip' => '198501152010011013',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);

        $mockStorage = Mockery::mock(KeycloakTokenStorageInterface::class);
        $mockStorage->shouldReceive('getAccessTokenExpiry')
            ->andReturn(now()->addSeconds(30));
        $mockStorage->shouldReceive('getRefreshToken')
            ->andReturn('some-refresh-token');
        $mockStorage->shouldReceive('isTokenValid')->andReturn(false);
        $mockStorage->shouldReceive('clearTokens')->once();

        $mockCircuitBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $mockCircuitBreaker->shouldReceive('call')
            ->andThrow(new KeycloakCircuitOpenException('Circuit is open'));
        $mockCircuitBreaker->shouldReceive('isOpen')->andReturn(true);

        $this->app->instance(KeycloakTokenStorageInterface::class, $mockStorage);
        $this->app->instance(CircuitBreakerInterface::class, $mockCircuitBreaker);

        $response = $this->actingAs($pegawai)
            ->withSession([
                'keycloak.tokens' => [
                    'access_token' => 'expired-token',
                    'refresh_token' => 'encrypted',
                    'expires_at' => now()->subSeconds(10)->toIso8601String(),
                ],
                'keycloak.user' => ['sub' => 'uuid', 'nip' => '198501152010011013', 'email' => 'e@e.id', 'name' => 'Test'],
                'keycloak.permissions' => [],
                'keycloak.roles' => [],
            ])
            ->get('/_test/protected');

        // Harus redirect ke login karena token expired + Keycloak unavailable
        $response->assertRedirect(route('keycloak.login'));
    });
});

// ============================================================================
// Flow 3: Logout → Session Cleared → Keycloak Notified
// ============================================================================

describe('logout → session cleared → Keycloak notified (Req 3.4, 3.5)', function () {
    test('complete logout: end-session dipanggil, tokens cleared, session invalidated', function () {
        $pegawai = Pegawai::factory()->create([
            'nip' => '198501152010011020',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);

        $mockClient = Mockery::mock(KeycloakClientInterface::class);
        $mockClient->shouldReceive('logout')
            ->once()
            ->with('current-refresh-token');

        $mockStorage = Mockery::mock(KeycloakTokenStorageInterface::class);
        $mockStorage->shouldReceive('getRefreshToken')->andReturn('current-refresh-token');
        $mockStorage->shouldReceive('clearTokens')->once();

        $this->app->instance(KeycloakClientInterface::class, $mockClient);
        $this->app->instance(KeycloakTokenStorageInterface::class, $mockStorage);

        // Login dulu
        $this->actingAs($pegawai);
        session()->put('keycloak.user', [
            'sub' => 'uuid-logout-test',
            'nip' => '198501152010011020',
            'email' => 'logout@test.go.id',
            'name' => 'Logout Test',
        ]);
        session()->put('keycloak.permissions', ['cuti.view']);
        session()->put('keycloak.roles', ['pegawai']);

        // Eksekusi logout
        $response = $this->post('/keycloak/logout');

        // Verifikasi redirect
        $response->assertRedirect('/');

        // Verifikasi user tidak lagi terautentikasi
        expect(Auth::check())->toBeFalse();
    });

    test('logout graceful: Keycloak unreachable → tetap clear lokal (Req 3.5)', function () {
        $pegawai = Pegawai::factory()->create([
            'nip' => '198501152010011021',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);

        $mockClient = Mockery::mock(KeycloakClientInterface::class);
        $mockClient->shouldReceive('logout')
            ->once()
            ->andThrow(new KeycloakException('Connection refused', KeycloakException::LOGOUT_FAILED));

        $mockStorage = Mockery::mock(KeycloakTokenStorageInterface::class);
        $mockStorage->shouldReceive('getRefreshToken')->andReturn('some-refresh');
        $mockStorage->shouldReceive('clearTokens')->once();

        $this->app->instance(KeycloakClientInterface::class, $mockClient);
        $this->app->instance(KeycloakTokenStorageInterface::class, $mockStorage);

        $response = $this->actingAs($pegawai)->post('/keycloak/logout');

        // Harus tetap berhasil logout meski Keycloak tidak tersedia
        $response->assertRedirect('/');
        expect(Auth::check())->toBeFalse();
    });

    test('logout tanpa refresh token → skip end-session, tetap clear lokal', function () {
        $pegawai = Pegawai::factory()->create([
            'nip' => '198501152010011022',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);

        $mockClient = Mockery::mock(KeycloakClientInterface::class);
        $mockClient->shouldNotReceive('logout');

        $mockStorage = Mockery::mock(KeycloakTokenStorageInterface::class);
        $mockStorage->shouldReceive('getRefreshToken')->andReturn(null);
        $mockStorage->shouldReceive('clearTokens')->once();

        $this->app->instance(KeycloakClientInterface::class, $mockClient);
        $this->app->instance(KeycloakTokenStorageInterface::class, $mockStorage);

        $response = $this->actingAs($pegawai)->post('/keycloak/logout');

        $response->assertRedirect('/');
        expect(Auth::check())->toBeFalse();
    });
});

// ============================================================================
// Flow 4: Emergency Bypass Flow saat Circuit Open
// ============================================================================

describe('emergency bypass flow saat circuit open (Req 10.1-10.8)', function () {
    test('emergency login form tersedia saat circuit OPEN dan enabled', function () {
        $mockCircuitBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $mockCircuitBreaker->shouldReceive('isOpen')->andReturn(true);
        $this->app->instance(CircuitBreakerInterface::class, $mockCircuitBreaker);

        config()->set('keycloak.emergency.enabled', true);

        $response = $this->get('/emergency/login');

        $response->assertStatus(200);
        $response->assertViewIs('auth.emergency-login');
    });

    test('emergency login redirect ke normal login saat circuit CLOSED (Req 10.2)', function () {
        $mockCircuitBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $mockCircuitBreaker->shouldReceive('isOpen')->andReturn(false);
        $this->app->instance(CircuitBreakerInterface::class, $mockCircuitBreaker);

        $response = $this->get('/emergency/login');

        $response->assertRedirect(route('keycloak.login'));
    });

    test('emergency login 503 saat circuit OPEN tapi disabled (Req 10.3)', function () {
        $mockCircuitBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $mockCircuitBreaker->shouldReceive('isOpen')->andReturn(true);
        $this->app->instance(CircuitBreakerInterface::class, $mockCircuitBreaker);

        config()->set('keycloak.emergency.enabled', false);

        $response = $this->get('/emergency/login');

        $response->assertStatus(503);
    });

    test('emergency login berhasil dengan credential valid → session terbatas (Req 10.1, 10.4, 10.5)', function () {
        $hashedPassword = Hash::make('emergency-secret-pass');

        $mockCircuitBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $mockCircuitBreaker->shouldReceive('isOpen')->andReturn(true);
        $this->app->instance(CircuitBreakerInterface::class, $mockCircuitBreaker);

        config()->set('keycloak.emergency.enabled', true);
        config()->set('keycloak.emergency.username', 'emergency_admin');
        config()->set('keycloak.emergency.password', $hashedPassword);
        config()->set('keycloak.emergency.session_timeout_minutes', 30);
        config()->set('keycloak.emergency.allowed_roles', ['admin']);

        $response = $this->post('/emergency/login', [
            'username' => 'emergency_admin',
            'password' => 'emergency-secret-pass',
        ]);

        // Berhasil redirect ke dashboard
        $response->assertRedirect(route('dashboard'));

        // Session emergency dibuat dengan timeout 30 menit
        $emergencySession = session('keycloak.emergency');
        expect($emergencySession)->not->toBeNull()
            ->and($emergencySession['authenticated'])->toBeTrue()
            ->and($emergencySession['roles'])->toBe(['admin'])
            ->and($emergencySession['expires_at'])->not->toBeNull();
    });

    test('emergency login gagal dengan credential salah → reject + rate limit (Req 10.7)', function () {
        $hashedPassword = Hash::make('correct-password');

        $mockCircuitBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $mockCircuitBreaker->shouldReceive('isOpen')->andReturn(true);
        $this->app->instance(CircuitBreakerInterface::class, $mockCircuitBreaker);

        config()->set('keycloak.emergency.enabled', true);
        config()->set('keycloak.emergency.username', 'admin_user');
        config()->set('keycloak.emergency.password', $hashedPassword);
        config()->set('keycloak.emergency.rate_limit_max_attempts', 5);
        config()->set('keycloak.emergency.rate_limit_decay_minutes', 15);

        $response = $this->post('/emergency/login', [
            'username' => 'admin_user',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect(route('emergency.login.form'));
        $response->assertSessionHasErrors('credentials');

        // Session emergency TIDAK dibuat
        expect(session('keycloak.emergency'))->toBeNull();
    });

    test('emergency login di-rate-limit setelah max attempts (Req 10.7)', function () {
        $hashedPassword = Hash::make('correct-password');

        $mockCircuitBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $mockCircuitBreaker->shouldReceive('isOpen')->andReturn(true);
        $this->app->instance(CircuitBreakerInterface::class, $mockCircuitBreaker);

        config()->set('keycloak.emergency.enabled', true);
        config()->set('keycloak.emergency.username', 'admin_user');
        config()->set('keycloak.emergency.password', $hashedPassword);
        config()->set('keycloak.emergency.rate_limit_max_attempts', 3);
        config()->set('keycloak.emergency.rate_limit_decay_minutes', 15);

        // Lakukan 3 kali percobaan gagal
        for ($i = 0; $i < 3; $i++) {
            $this->post('/emergency/login', [
                'username' => 'admin_user',
                'password' => 'wrong-password',
            ]);
        }

        // Percobaan ke-4 harus di-rate-limit
        $response = $this->post('/emergency/login', [
            'username' => 'admin_user',
            'password' => 'wrong-password',
        ]);

        $response->assertRedirect(route('emergency.login.form'));
        $response->assertSessionHasErrors('credentials');
    });

    test('emergency login di-log ke database (Req 10.6)', function () {
        $hashedPassword = Hash::make('secret123');

        $mockCircuitBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $mockCircuitBreaker->shouldReceive('isOpen')->andReturn(true);
        $this->app->instance(CircuitBreakerInterface::class, $mockCircuitBreaker);

        config()->set('keycloak.emergency.enabled', true);
        config()->set('keycloak.emergency.username', 'log_admin');
        config()->set('keycloak.emergency.password', $hashedPassword);

        // Login berhasil
        $this->post('/emergency/login', [
            'username' => 'log_admin',
            'password' => 'secret123',
        ]);

        // Verifikasi log tercatat di database
        $this->assertDatabaseHas('keycloak_emergency_login_log', [
            'username' => hash('sha256', 'log_admin'),
        ]);
    });
});
