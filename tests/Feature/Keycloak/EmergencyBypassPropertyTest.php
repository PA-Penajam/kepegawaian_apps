<?php

/**
 * Property-Based Tests untuk Emergency Bypass.
 *
 * Menguji properti universal dari emergency bypass logic:
 * - Property 19: Emergency Access Guard (Req 10.1, 10.2, 10.3)
 * - Property 20: Emergency Session Timeout (Req 10.4)
 * - Property 21: Emergency Audit Trail (Req 10.6)
 */

use App\Keycloak\Contracts\CircuitBreakerInterface;
use App\Keycloak\Models\KeycloakEmergencyLoginLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

// ============================================================
// Helper Functions untuk Property Testing
// ============================================================

/**
 * Menghasilkan username acak untuk testing.
 */
function generateRandomUsername(): string
{
    $strategies = [
        fn () => 'user_'.bin2hex(random_bytes(random_int(2, 8))),
        fn () => fake()->userName(),
        fn () => fake()->firstName().random_int(1, 999),
        fn () => bin2hex(random_bytes(random_int(4, 16))),
        fn () => 'admin_'.random_int(1, 9999),
    ];

    return $strategies[array_rand($strategies)]();
}

/**
 * Menghasilkan password acak untuk testing.
 */
function generateRandomPassword(): string
{
    $strategies = [
        fn () => bin2hex(random_bytes(random_int(4, 16))),
        fn () => fake()->password(8, 32),
        fn () => str_repeat('a', random_int(1, 50)),
        fn () => 'P@ss'.random_int(100, 9999).'!',
        fn () => fake()->sentence(random_int(2, 5)),
    ];

    return $strategies[array_rand($strategies)]();
}

/**
 * Menghasilkan IP address acak.
 */
function generateRandomIp(): string
{
    return random_int(1, 254).'.'.random_int(0, 254).'.'.random_int(0, 254).'.'.random_int(1, 254);
}

/**
 * Menghasilkan user agent acak.
 */
function generateRandomUserAgent(): string
{
    $agents = [
        'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36',
        'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15) AppleWebKit/605.1.15',
        'Mozilla/5.0 (X11; Linux x86_64; rv:'.random_int(80, 120).'.0) Gecko/20100101',
        'TestAgent/'.random_int(1, 9).'.'.random_int(0, 9),
        'curl/'.random_int(7, 8).'.'.random_int(0, 88).'.'.random_int(0, 5),
        fake()->userAgent(),
    ];

    return $agents[array_rand($agents)];
}

/**
 * Membuat mock CircuitBreaker untuk testing.
 */
function createCircuitBreakerMock(bool $isOpen): CircuitBreakerInterface
{
    $mock = Mockery::mock(CircuitBreakerInterface::class);
    $mock->shouldReceive('isOpen')->andReturn($isOpen);

    return $mock;
}

// ============================================================
// Setup
// ============================================================

beforeEach(function () {
    // Konfigurasi emergency login
    config()->set('keycloak.emergency.enabled', true);
    config()->set('keycloak.emergency.username', 'emergency_admin');
    config()->set('keycloak.emergency.password', Hash::make('secret_pass_123'));
    config()->set('keycloak.emergency.session_timeout_minutes', 30);
    config()->set('keycloak.emergency.allowed_roles', ['admin']);
    config()->set('keycloak.emergency.rate_limit_max_attempts', 5);
    config()->set('keycloak.emergency.rate_limit_decay_minutes', 15);
});

// ============================================================
// Property 19: Emergency Access Guard
// **Validates: Requirements 10.1, 10.2, 10.3**
// ============================================================

describe('Property 19: Emergency Access Guard', function () {
    test('akses HANYA diberikan saat circuit OPEN, emergency enabled, DAN kredensial valid', function () {
        // UNTUK SEMUA kombinasi: circuit OPEN + emergency enabled + kredensial valid
        // → akses SELALU diberikan (redirect ke dashboard)
        $mockBreaker = createCircuitBreakerMock(true);
        $this->app->instance(CircuitBreakerInterface::class, $mockBreaker);

        for ($i = 0; $i < 30; $i++) {
            $response = $this->post('/emergency/login', [
                'username' => 'emergency_admin',
                'password' => 'secret_pass_123',
            ]);

            $response->assertRedirect(route('dashboard'));

            // Verifikasi session terbuat
            $emergencySession = session('keycloak.emergency');
            expect($emergencySession)
                ->not->toBeNull()
                ->and($emergencySession['authenticated'])->toBeTrue();

            // Reset session untuk iterasi berikutnya
            session()->flush();
        }
    });

    test('akses SELALU ditolak saat circuit TIDAK open (Req 10.2)', function () {
        // UNTUK SEMUA percobaan login saat circuit CLOSED/HALF_OPEN,
        // sistem SELALU redirect ke Keycloak login.
        $mockBreaker = createCircuitBreakerMock(false);
        $this->app->instance(CircuitBreakerInterface::class, $mockBreaker);

        for ($i = 0; $i < 30; $i++) {
            $username = ($i % 2 === 0) ? 'emergency_admin' : generateRandomUsername();
            $password = ($i % 2 === 0) ? 'secret_pass_123' : generateRandomPassword();

            // POST: redirect ke keycloak login
            $response = $this->post('/emergency/login', [
                'username' => $username,
                'password' => $password,
            ]);

            $response->assertRedirect(route('keycloak.login'));

            // GET form: juga redirect ke keycloak login
            $response = $this->get('/emergency/login');
            $response->assertRedirect(route('keycloak.login'));

            // Session emergency TIDAK boleh ada
            expect(session('keycloak.emergency'))->toBeNull();
        }
    });

    test('akses SELALU ditolak saat emergency disabled (Req 10.3)', function () {
        // UNTUK SEMUA percobaan login saat emergency disabled,
        // sistem SELALU return 503.
        config()->set('keycloak.emergency.enabled', false);

        $mockBreaker = createCircuitBreakerMock(true);
        $this->app->instance(CircuitBreakerInterface::class, $mockBreaker);

        for ($i = 0; $i < 30; $i++) {
            $username = ($i % 2 === 0) ? 'emergency_admin' : generateRandomUsername();
            $password = ($i % 2 === 0) ? 'secret_pass_123' : generateRandomPassword();

            // POST: 503
            $response = $this->post('/emergency/login', [
                'username' => $username,
                'password' => $password,
            ]);

            $response->assertStatus(503);

            // GET form: 503
            $response = $this->get('/emergency/login');
            $response->assertStatus(503);

            // Session emergency TIDAK boleh ada
            expect(session('keycloak.emergency'))->toBeNull();
        }
    });

    test('kredensial acak yang tidak valid SELALU ditolak saat circuit open', function () {
        // UNTUK SEMUA kombinasi username/password acak yang bukan kredensial configured,
        // sistem SELALU menolak dengan error.
        $mockBreaker = createCircuitBreakerMock(true);
        $this->app->instance(CircuitBreakerInterface::class, $mockBreaker);

        for ($i = 0; $i < 30; $i++) {
            $username = generateRandomUsername();
            $password = generateRandomPassword();

            // Pastikan bukan kredensial yang valid
            if ($username === 'emergency_admin' && $password === 'secret_pass_123') {
                continue;
            }

            $response = $this->post('/emergency/login', [
                'username' => $username,
                'password' => $password,
            ]);

            $response->assertRedirect(route('emergency.login.form'));
            $response->assertSessionHasErrors('credentials');

            // Session emergency TIDAK boleh ada
            expect(session('keycloak.emergency'))->toBeNull();

            // Reset rate limiter untuk iterasi berikutnya
            RateLimiter::clear('emergency_login:127.0.0.1');
        }
    });

    test('username benar + password salah SELALU ditolak', function () {
        // UNTUK SEMUA kombinasi username valid + password acak,
        // sistem SELALU menolak.
        $mockBreaker = createCircuitBreakerMock(true);
        $this->app->instance(CircuitBreakerInterface::class, $mockBreaker);

        for ($i = 0; $i < 30; $i++) {
            $password = generateRandomPassword();

            // Pastikan bukan password yang valid
            if ($password === 'secret_pass_123') {
                continue;
            }

            $response = $this->post('/emergency/login', [
                'username' => 'emergency_admin',
                'password' => $password,
            ]);

            $response->assertRedirect(route('emergency.login.form'));
            $response->assertSessionHasErrors('credentials');

            expect(session('keycloak.emergency'))->toBeNull();

            RateLimiter::clear('emergency_login:127.0.0.1');
        }
    });
});

// ============================================================
// Property 20: Emergency Session Timeout
// **Validates: Requirement 10.4**
// ============================================================

describe('Property 20: Emergency Session Timeout', function () {
    test('session SELALU memiliki expiry tepat 30 menit dari login', function () {
        // UNTUK SEMUA emergency login yang berhasil,
        // session expires_at SELALU tepat 30 menit dari waktu login.
        $mockBreaker = createCircuitBreakerMock(true);
        $this->app->instance(CircuitBreakerInterface::class, $mockBreaker);

        for ($i = 0; $i < 30; $i++) {
            $this->freezeTime();

            $response = $this->post('/emergency/login', [
                'username' => 'emergency_admin',
                'password' => 'secret_pass_123',
            ]);

            $response->assertRedirect(route('dashboard'));

            $emergencySession = session('keycloak.emergency');
            expect($emergencySession)->not->toBeNull();

            // expires_at harus tepat 30 menit dari sekarang
            $expectedExpiry = now()->addMinutes(30);
            $actualExpiry = $emergencySession['expires_at'];

            expect($actualExpiry->toIso8601String())
                ->toBe($expectedExpiry->toIso8601String());

            // Verifikasi selisih tepat 30 menit dari logged_in_at
            $loggedInAt = $emergencySession['logged_in_at'];
            $diffMinutes = (int) $loggedInAt->diffInMinutes($actualExpiry);
            expect($diffMinutes)->toBe(30);

            // Reset untuk iterasi berikutnya
            session()->flush();
            $this->travelBack();
        }
    });

    test('session timeout konsisten dengan konfigurasi yang berbeda', function () {
        // UNTUK SEMUA nilai timeout konfigurasi acak,
        // session expires_at SELALU sesuai dengan konfigurasi.
        $mockBreaker = createCircuitBreakerMock(true);
        $this->app->instance(CircuitBreakerInterface::class, $mockBreaker);

        $timeoutValues = [5, 10, 15, 20, 25, 30, 45, 60, 90, 120];

        foreach ($timeoutValues as $timeout) {
            config()->set('keycloak.emergency.session_timeout_minutes', $timeout);

            $this->freezeTime();

            $response = $this->post('/emergency/login', [
                'username' => 'emergency_admin',
                'password' => 'secret_pass_123',
            ]);

            $response->assertRedirect(route('dashboard'));

            $emergencySession = session('keycloak.emergency');
            $expectedExpiry = now()->addMinutes($timeout);

            expect($emergencySession['expires_at']->toIso8601String())
                ->toBe($expectedExpiry->toIso8601String());

            session()->flush();
            $this->travelBack();
        }
    });

    test('session SELALU memiliki logged_in_at yang sesuai dengan waktu login', function () {
        // UNTUK SEMUA emergency login yang berhasil,
        // logged_in_at SELALU sesuai dengan waktu saat login dilakukan.
        $mockBreaker = createCircuitBreakerMock(true);
        $this->app->instance(CircuitBreakerInterface::class, $mockBreaker);

        for ($i = 0; $i < 20; $i++) {
            $this->freezeTime();

            $response = $this->post('/emergency/login', [
                'username' => 'emergency_admin',
                'password' => 'secret_pass_123',
            ]);

            $response->assertRedirect(route('dashboard'));

            $emergencySession = session('keycloak.emergency');
            expect($emergencySession['logged_in_at']->toIso8601String())
                ->toBe(now()->toIso8601String());

            session()->flush();
            $this->travelBack();
        }
    });
});

// ============================================================
// Property 21: Emergency Audit Trail
// **Validates: Requirement 10.6**
// ============================================================

describe('Property 21: Emergency Audit Trail', function () {
    test('login berhasil SELALU menghasilkan log entry dengan data lengkap', function () {
        // UNTUK SEMUA emergency login yang berhasil,
        // log entry SELALU ada dengan hashed username, IP, user_agent, timestamp.
        $mockBreaker = createCircuitBreakerMock(true);
        $this->app->instance(CircuitBreakerInterface::class, $mockBreaker);

        for ($i = 0; $i < 30; $i++) {
            $userAgent = generateRandomUserAgent();

            $this->withHeaders(['User-Agent' => $userAgent])
                ->post('/emergency/login', [
                    'username' => 'emergency_admin',
                    'password' => 'secret_pass_123',
                ]);

            $log = KeycloakEmergencyLoginLog::latest('id')->first();

            expect($log)->not->toBeNull()
                ->and($log->username)->toBe(hash('sha256', 'emergency_admin'))
                ->and($log->ip_address)->toBe('127.0.0.1')
                ->and($log->user_agent)->toBe(mb_substr($userAgent, 0, 512))
                ->and($log->logged_in_at)->not->toBeNull()
                ->and($log->outcome)->toBe('success');

            session()->flush();
        }
    });

    test('login gagal SELALU menghasilkan log entry dengan data lengkap', function () {
        // UNTUK SEMUA emergency login yang gagal,
        // log entry SELALU ada dengan hashed username, IP, user_agent, timestamp.
        $mockBreaker = createCircuitBreakerMock(true);
        $this->app->instance(CircuitBreakerInterface::class, $mockBreaker);

        for ($i = 0; $i < 30; $i++) {
            $username = generateRandomUsername();
            $password = generateRandomPassword();
            $userAgent = generateRandomUserAgent();

            // Pastikan bukan kredensial valid
            if ($username === 'emergency_admin' && $password === 'secret_pass_123') {
                continue;
            }

            $this->withHeaders(['User-Agent' => $userAgent])
                ->post('/emergency/login', [
                    'username' => $username,
                    'password' => $password,
                ]);

            $log = KeycloakEmergencyLoginLog::latest('id')->first();

            expect($log)->not->toBeNull()
                ->and($log->username)->toBe(hash('sha256', $username))
                ->and($log->ip_address)->toBe('127.0.0.1')
                ->and($log->user_agent)->toBe(mb_substr($userAgent, 0, 512))
                ->and($log->logged_in_at)->not->toBeNull()
                ->and($log->outcome)->toBe('failure');

            // Reset rate limiter untuk iterasi berikutnya
            RateLimiter::clear('emergency_login:127.0.0.1');
        }
    });

    test('username SELALU disimpan sebagai hash SHA-256 di log', function () {
        // UNTUK SEMUA username (acak), log SELALU menyimpan hash(sha256) bukan plaintext.
        $mockBreaker = createCircuitBreakerMock(true);
        $this->app->instance(CircuitBreakerInterface::class, $mockBreaker);

        for ($i = 0; $i < 30; $i++) {
            $username = generateRandomUsername();

            $this->post('/emergency/login', [
                'username' => $username,
                'password' => generateRandomPassword(),
            ]);

            $log = KeycloakEmergencyLoginLog::latest('id')->first();

            // Username TIDAK boleh plaintext di database
            expect($log->username)
                ->not->toBe($username)
                ->and($log->username)->toBe(hash('sha256', $username))
                ->and(strlen($log->username))->toBe(64); // SHA-256 menghasilkan 64 hex chars

            RateLimiter::clear('emergency_login:127.0.0.1');
        }
    });

    test('setiap percobaan login menghasilkan TEPAT satu log entry', function () {
        // UNTUK SEMUA percobaan login (berhasil atau gagal),
        // TEPAT satu log entry ditambahkan ke database.
        $mockBreaker = createCircuitBreakerMock(true);
        $this->app->instance(CircuitBreakerInterface::class, $mockBreaker);

        $initialCount = KeycloakEmergencyLoginLog::count();

        for ($i = 0; $i < 20; $i++) {
            $isValid = $i % 3 === 0;
            $username = $isValid ? 'emergency_admin' : generateRandomUsername();
            $password = $isValid ? 'secret_pass_123' : generateRandomPassword();

            $this->post('/emergency/login', [
                'username' => $username,
                'password' => $password,
            ]);

            $expectedCount = $initialCount + $i + 1;
            expect(KeycloakEmergencyLoginLog::count())->toBe($expectedCount);

            session()->flush();
            RateLimiter::clear('emergency_login:127.0.0.1');
        }
    });

    test('timestamp log SELALU mencatat waktu percobaan login', function () {
        // UNTUK SEMUA percobaan login,
        // logged_in_at SELALU sesuai dengan waktu percobaan.
        $mockBreaker = createCircuitBreakerMock(true);
        $this->app->instance(CircuitBreakerInterface::class, $mockBreaker);

        for ($i = 0; $i < 20; $i++) {
            $this->freezeTime();

            $this->post('/emergency/login', [
                'username' => generateRandomUsername(),
                'password' => generateRandomPassword(),
            ]);

            $log = KeycloakEmergencyLoginLog::latest('id')->first();
            expect($log->logged_in_at->toIso8601String())
                ->toBe(now()->toIso8601String());

            RateLimiter::clear('emergency_login:127.0.0.1');
            $this->travelBack();
        }
    });
});
