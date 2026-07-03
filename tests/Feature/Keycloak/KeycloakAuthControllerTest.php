<?php

use App\Enums\StatusPegawai;
use App\Keycloak\Contracts\KeycloakClientInterface;
use App\Keycloak\Contracts\KeycloakTokenStorageInterface;
use App\Keycloak\DataTransferObjects\AuthorizationRequest;
use App\Keycloak\DataTransferObjects\IdTokenClaims;
use App\Keycloak\DataTransferObjects\PkcePair;
use App\Keycloak\DataTransferObjects\TokenResult;
use App\Keycloak\Exceptions\KeycloakException;
use App\Models\Pegawai;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;

// === Login Tests ===

describe('login', function () {
    test('redirect ke Keycloak authorization URL', function () {
        $authRequest = new AuthorizationRequest(
            url: 'http://keycloak.test/realms/kepegawaian/protocol/openid-connect/auth?client_id=test',
            state: bin2hex(random_bytes(32)),
            pkce: new PkcePair(
                verifier: 'test-verifier-123',
                challenge: 'test-challenge-456',
                method: 'S256',
            ),
        );

        $mockClient = Mockery::mock(KeycloakClientInterface::class);
        $mockClient->shouldReceive('buildAuthorizationUrl')
            ->once()
            ->andReturn($authRequest);

        $this->app->instance(KeycloakClientInterface::class, $mockClient);

        $response = $this->get('/keycloak/login');

        $response->assertRedirect($authRequest->url);
    });

    test('menyimpan state dan code_verifier di session', function () {
        $state = bin2hex(random_bytes(32));
        $authRequest = new AuthorizationRequest(
            url: 'http://keycloak.test/auth',
            state: $state,
            pkce: new PkcePair(
                verifier: 'verifier-abc',
                challenge: 'challenge-xyz',
                method: 'S256',
            ),
        );

        $mockClient = Mockery::mock(KeycloakClientInterface::class);
        $mockClient->shouldReceive('buildAuthorizationUrl')->andReturn($authRequest);

        $this->app->instance(KeycloakClientInterface::class, $mockClient);

        $this->get('/keycloak/login');

        // Verifikasi session berisi data oauth_state
        $oauthState = session('keycloak.oauth_state');
        expect($oauthState)
            ->not->toBeNull()
            ->and($oauthState['state'])->toBe($state)
            ->and($oauthState['code_verifier'])->toBe('verifier-abc')
            ->and($oauthState['expires_at'])->not->toBeNull();
    });

    test('session oauth_state memiliki expiry 10 menit kedepan', function () {
        $authRequest = new AuthorizationRequest(
            url: 'http://keycloak.test/auth',
            state: bin2hex(random_bytes(32)),
            pkce: new PkcePair('v', 'c', 'S256'),
        );

        $mockClient = Mockery::mock(KeycloakClientInterface::class);
        $mockClient->shouldReceive('buildAuthorizationUrl')->andReturn($authRequest);

        $this->app->instance(KeycloakClientInterface::class, $mockClient);

        $this->get('/keycloak/login');

        $oauthState = session('keycloak.oauth_state');
        $expiresAt = Carbon::parse($oauthState['expires_at']);

        // Expiry harus sekitar 10 menit dari sekarang
        $diffMinutes = now()->diffInMinutes($expiresAt, false);
        expect($diffMinutes)->toBeLessThanOrEqual(10)
            ->and($diffMinutes)->toBeGreaterThanOrEqual(9);
    });
});

// === Callback Tests ===

describe('callback', function () {
    test('redirect ke login jika state tidak valid', function () {
        $response = $this->withSession([
            'keycloak.oauth_state' => [
                'state' => 'stored-state-value',
                'code_verifier' => 'verifier',
                'expires_at' => now()->addMinutes(10)->toIso8601String(),
            ],
        ])->get('/keycloak/callback?code=abc&state=different-state');

        // Req 1.5: Invalid state → abort 403
        $response->assertStatus(403);
    });

    test('redirect ke login jika session tidak memiliki oauth_state', function () {
        $response = $this->get('/keycloak/callback?code=abc&state=some-state');

        // Req 1.5: Invalid state (no session) → abort 403
        $response->assertStatus(403);
    });

    test('redirect ke login jika state sudah expired', function () {
        $response = $this->withSession([
            'keycloak.oauth_state' => [
                'state' => 'valid-state',
                'code_verifier' => 'verifier',
                'expires_at' => now()->subMinutes(1)->toIso8601String(),
            ],
        ])->get('/keycloak/callback?code=abc&state=valid-state');

        // Req 1.5: State expired → abort 403
        $response->assertStatus(403);
    });

    test('redirect ke login jika Keycloak mengembalikan error', function () {
        $response = $this->get('/keycloak/callback?error=access_denied&error_description=User+denied+access');

        $response->assertRedirect(route('keycloak.login'));
        $response->assertSessionHas('error', 'User denied access');
    });

    test('redirect ke login jika token exchange gagal', function () {
        $state = bin2hex(random_bytes(32));

        $mockClient = Mockery::mock(KeycloakClientInterface::class);
        $mockClient->shouldReceive('exchangeCode')
            ->once()
            ->andThrow(new KeycloakException('Exchange failed', KeycloakException::CODE_EXCHANGE_FAILED));

        $this->app->instance(KeycloakClientInterface::class, $mockClient);

        $response = $this->withSession([
            'keycloak.oauth_state' => [
                'state' => $state,
                'code_verifier' => 'verifier',
                'expires_at' => now()->addMinutes(10)->toIso8601String(),
            ],
        ])->get("/keycloak/callback?code=abc&state={$state}");

        $response->assertRedirect(route('keycloak.login'));
        $response->assertSessionHas('error', 'Autentikasi gagal: tidak dapat memperoleh token. Silakan coba login kembali.');
    });

    test('redirect ke login jika validasi ID token gagal', function () {
        $state = bin2hex(random_bytes(32));

        $tokenResult = new TokenResult(
            accessToken: 'access-token',
            refreshToken: 'refresh-token',
            idToken: 'invalid-id-token',
            expiresIn: 300,
            refreshExpiresIn: 1800,
            tokenType: 'Bearer',
        );

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
        $response->assertSessionHas('error', 'Token identitas tidak valid. Silakan coba login kembali.');
    });

    test('redirect ke login jika NIP format tidak valid', function () {
        $state = bin2hex(random_bytes(32));

        $tokenResult = new TokenResult('at', 'rt', 'it', 300, 1800, 'Bearer');
        $claims = new IdTokenClaims(
            sub: 'uuid-123',
            nip: '123', // NIP tidak valid (bukan 18 digit)
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
    });

    test('redirect ke login jika NIP tidak terdaftar di Pegawai', function () {
        $state = bin2hex(random_bytes(32));

        $tokenResult = new TokenResult('at', 'rt', 'it', 300, 1800, 'Bearer');
        $claims = new IdTokenClaims(
            sub: 'uuid-123',
            nip: '198501152010011001',
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
    });

    test('redirect ke login jika Pegawai tidak aktif', function () {
        $state = bin2hex(random_bytes(32));
        $pegawai = Pegawai::factory()->create([
            'nip' => '198501152010011001',
            'status_pegawai' => StatusPegawai::Pensiun,
        ]);

        $tokenResult = new TokenResult('at', 'rt', 'it', 300, 1800, 'Bearer');
        $claims = new IdTokenClaims(
            sub: 'uuid-123',
            nip: '198501152010011001',
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
    });

    test('berhasil login Pegawai aktif dan redirect ke dashboard', function () {
        $state = bin2hex(random_bytes(32));
        $pegawai = Pegawai::factory()->create([
            'nip' => '198501152010011001',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);

        $tokenResult = new TokenResult('at', 'rt', 'it', 300, 1800, 'Bearer');
        $claims = new IdTokenClaims(
            sub: 'uuid-123',
            nip: '198501152010011001',
            email: 'pegawai@email.com',
            name: 'Pegawai Test',
            roles: ['pegawai', 'operator'],
            permissions: ['cuti.view', 'cuti.create'],
            exp: time() + 3600,
            iat: time(),
            iss: 'http://keycloak.test/realms/kepegawaian',
        );

        $mockClient = Mockery::mock(KeycloakClientInterface::class);
        $mockClient->shouldReceive('exchangeCode')->andReturn($tokenResult);
        $mockClient->shouldReceive('validateIdToken')->andReturn($claims);

        $mockStorage = Mockery::mock(KeycloakTokenStorageInterface::class);
        $mockStorage->shouldReceive('storeTokens')->once()->with($tokenResult);

        $this->app->instance(KeycloakClientInterface::class, $mockClient);
        $this->app->instance(KeycloakTokenStorageInterface::class, $mockStorage);

        $response = $this->withSession([
            'keycloak.oauth_state' => [
                'state' => $state,
                'code_verifier' => 'verifier',
                'expires_at' => now()->addMinutes(10)->toIso8601String(),
            ],
        ])->get("/keycloak/callback?code=auth-code&state={$state}");

        $response->assertRedirect('/dashboard');

        // Verifikasi user terautentikasi
        expect(Auth::check())->toBeTrue()
            ->and(Auth::id())->toBe($pegawai->id);

        // Verifikasi session berisi user claims
        expect(session('keycloak.user'))->toBe([
            'sub' => 'uuid-123',
            'nip' => '198501152010011001',
            'email' => 'pegawai@email.com',
            'name' => 'Pegawai Test',
        ]);

        // Verifikasi session berisi permissions dan roles
        expect(session('keycloak.permissions'))->toBe(['cuti.view', 'cuti.create'])
            ->and(session('keycloak.roles'))->toBe(['pegawai', 'operator']);
    });

    test('menghapus oauth_state dari session setelah diproses (cegah replay)', function () {
        $state = bin2hex(random_bytes(32));
        $pegawai = Pegawai::factory()->create([
            'nip' => '198501152010011002',
            'status_pegawai' => StatusPegawai::Aktif,
        ]);

        $tokenResult = new TokenResult('at', 'rt', 'it', 300, 1800, 'Bearer');
        $claims = new IdTokenClaims(
            sub: 'uuid-456',
            nip: '198501152010011002',
            email: 'test@email.com',
            name: 'Test',
            roles: [],
            permissions: [],
            exp: time() + 3600,
            iat: time(),
            iss: 'http://keycloak.test/realms/kepegawaian',
        );

        $mockClient = Mockery::mock(KeycloakClientInterface::class);
        $mockClient->shouldReceive('exchangeCode')->andReturn($tokenResult);
        $mockClient->shouldReceive('validateIdToken')->andReturn($claims);

        $mockStorage = Mockery::mock(KeycloakTokenStorageInterface::class);
        $mockStorage->shouldReceive('storeTokens');

        $this->app->instance(KeycloakClientInterface::class, $mockClient);
        $this->app->instance(KeycloakTokenStorageInterface::class, $mockStorage);

        $this->withSession([
            'keycloak.oauth_state' => [
                'state' => $state,
                'code_verifier' => 'verifier',
                'expires_at' => now()->addMinutes(10)->toIso8601String(),
            ],
        ])->get("/keycloak/callback?code=abc&state={$state}");

        // oauth_state harus sudah dihapus dari session
        expect(session('keycloak.oauth_state'))->toBeNull();
    });
});

// === Logout Tests ===

describe('logout', function () {
    test('invoke end-session endpoint dan clear session', function () {
        $pegawai = Pegawai::factory()->create([
            'status_pegawai' => StatusPegawai::Aktif,
        ]);

        $mockClient = Mockery::mock(KeycloakClientInterface::class);
        $mockClient->shouldReceive('logout')->once()->with('my-refresh-token');

        $mockStorage = Mockery::mock(KeycloakTokenStorageInterface::class);
        $mockStorage->shouldReceive('getRefreshToken')->andReturn('my-refresh-token');
        $mockStorage->shouldReceive('clearTokens')->once();

        $this->app->instance(KeycloakClientInterface::class, $mockClient);
        $this->app->instance(KeycloakTokenStorageInterface::class, $mockStorage);

        $response = $this->actingAs($pegawai)->post('/keycloak/logout');

        $response->assertRedirect('/');
        expect(Auth::check())->toBeFalse();
    });

    test('lanjutkan cleanup lokal jika Keycloak end-session gagal', function () {
        $pegawai = Pegawai::factory()->create([
            'status_pegawai' => StatusPegawai::Aktif,
        ]);

        $mockClient = Mockery::mock(KeycloakClientInterface::class);
        $mockClient->shouldReceive('logout')
            ->once()
            ->andThrow(new KeycloakException('Keycloak unreachable', KeycloakException::LOGOUT_FAILED));

        $mockStorage = Mockery::mock(KeycloakTokenStorageInterface::class);
        $mockStorage->shouldReceive('getRefreshToken')->andReturn('some-token');
        $mockStorage->shouldReceive('clearTokens')->once();

        $this->app->instance(KeycloakClientInterface::class, $mockClient);
        $this->app->instance(KeycloakTokenStorageInterface::class, $mockStorage);

        $response = $this->actingAs($pegawai)->post('/keycloak/logout');

        // Harus tetap berhasil redirect meski Keycloak gagal
        $response->assertRedirect('/');
        expect(Auth::check())->toBeFalse();
    });

    test('handle gracefully jika tidak ada refresh token', function () {
        $pegawai = Pegawai::factory()->create([
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
