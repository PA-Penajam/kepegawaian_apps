<?php

use App\Keycloak\Contracts\CircuitBreakerInterface;
use App\Keycloak\Models\KeycloakEmergencyLoginLog;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

beforeEach(function () {
    // Konfigurasi emergency login untuk testing
    // Password disimpan sebagai hash (Req 10.5: Hash::check untuk verifikasi)
    config()->set('keycloak.emergency.enabled', true);
    config()->set('keycloak.emergency.username', 'emergency_admin');
    config()->set('keycloak.emergency.password', Hash::make('secret_pass_123'));
    config()->set('keycloak.emergency.session_timeout_minutes', 30);
    config()->set('keycloak.emergency.allowed_roles', ['admin']);
    config()->set('keycloak.emergency.rate_limit_max_attempts', 5);
    config()->set('keycloak.emergency.rate_limit_decay_minutes', 15);
});

// === showLoginForm Tests ===

describe('showLoginForm', function () {
    test('menampilkan form login saat circuit breaker OPEN dan emergency enabled', function () {
        $mockBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $mockBreaker->shouldReceive('isOpen')->andReturn(true);
        $this->app->instance(CircuitBreakerInterface::class, $mockBreaker);

        $response = $this->get('/emergency/login');

        $response->assertStatus(200);
        $response->assertViewIs('auth.emergency-login');
    });

    test('redirect ke Keycloak login saat circuit breaker TIDAK open', function () {
        $mockBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $mockBreaker->shouldReceive('isOpen')->andReturn(false);
        $this->app->instance(CircuitBreakerInterface::class, $mockBreaker);

        $response = $this->get('/emergency/login');

        $response->assertRedirect(route('keycloak.login'));
        $response->assertSessionHas('info');
    });

    test('mengembalikan 503 saat emergency disabled', function () {
        config()->set('keycloak.emergency.enabled', false);

        $mockBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $mockBreaker->shouldReceive('isOpen')->andReturn(true);
        $this->app->instance(CircuitBreakerInterface::class, $mockBreaker);

        $response = $this->get('/emergency/login');

        $response->assertStatus(503);
    });
});

// === login Tests ===

describe('login', function () {
    test('berhasil login dengan kredensial valid dan circuit open', function () {
        $mockBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $mockBreaker->shouldReceive('isOpen')->andReturn(true);
        $this->app->instance(CircuitBreakerInterface::class, $mockBreaker);

        $response = $this->post('/emergency/login', [
            'username' => 'emergency_admin',
            'password' => 'secret_pass_123',
        ]);

        $response->assertRedirect(route('dashboard'));

        // Verifikasi session emergency dibuat
        $emergencySession = session('keycloak.emergency');
        expect($emergencySession)
            ->not->toBeNull()
            ->and($emergencySession['authenticated'])->toBeTrue()
            ->and($emergencySession['roles'])->toBe(['admin']);
    });

    test('emergency session memiliki expires_at 30 menit ke depan', function () {
        $mockBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $mockBreaker->shouldReceive('isOpen')->andReturn(true);
        $this->app->instance(CircuitBreakerInterface::class, $mockBreaker);

        $this->freezeTime();

        $this->post('/emergency/login', [
            'username' => 'emergency_admin',
            'password' => 'secret_pass_123',
        ]);

        $emergencySession = session('keycloak.emergency');
        expect((int) now()->diffInMinutes($emergencySession['expires_at']))->toBe(30);
    });

    test('menolak kredensial yang tidak valid', function () {
        $mockBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $mockBreaker->shouldReceive('isOpen')->andReturn(true);
        $this->app->instance(CircuitBreakerInterface::class, $mockBreaker);

        $response = $this->post('/emergency/login', [
            'username' => 'wrong_user',
            'password' => 'wrong_pass',
        ]);

        $response->assertRedirect(route('emergency.login.form'));
        $response->assertSessionHasErrors('credentials');

        // Session emergency TIDAK boleh ada
        expect(session('keycloak.emergency'))->toBeNull();
    });

    test('menolak password yang salah dengan username benar', function () {
        $mockBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $mockBreaker->shouldReceive('isOpen')->andReturn(true);
        $this->app->instance(CircuitBreakerInterface::class, $mockBreaker);

        $response = $this->post('/emergency/login', [
            'username' => 'emergency_admin',
            'password' => 'wrong_pass',
        ]);

        $response->assertRedirect(route('emergency.login.form'));
        $response->assertSessionHasErrors('credentials');
    });

    test('redirect ke Keycloak login saat circuit TIDAK open', function () {
        $mockBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $mockBreaker->shouldReceive('isOpen')->andReturn(false);
        $this->app->instance(CircuitBreakerInterface::class, $mockBreaker);

        $response = $this->post('/emergency/login', [
            'username' => 'emergency_admin',
            'password' => 'secret_pass_123',
        ]);

        $response->assertRedirect(route('keycloak.login'));
    });

    test('mengembalikan 503 saat emergency disabled pada POST', function () {
        config()->set('keycloak.emergency.enabled', false);

        $mockBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $mockBreaker->shouldReceive('isOpen')->andReturn(true);
        $this->app->instance(CircuitBreakerInterface::class, $mockBreaker);

        $response = $this->post('/emergency/login', [
            'username' => 'emergency_admin',
            'password' => 'secret_pass_123',
        ]);

        $response->assertStatus(503);
    });

    test('rate limiting memblokir setelah max attempts', function () {
        $mockBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $mockBreaker->shouldReceive('isOpen')->andReturn(true);
        $this->app->instance(CircuitBreakerInterface::class, $mockBreaker);

        // Lakukan 5 percobaan gagal
        for ($i = 0; $i < 5; $i++) {
            $this->post('/emergency/login', [
                'username' => 'wrong',
                'password' => 'wrong',
            ]);
        }

        // Percobaan ke-6 harus di-block oleh rate limiter
        $response = $this->post('/emergency/login', [
            'username' => 'emergency_admin',
            'password' => 'secret_pass_123',
        ]);

        $response->assertRedirect(route('emergency.login.form'));
        $response->assertSessionHasErrors('credentials');
    });

    test('rate limiter di-reset setelah login berhasil', function () {
        $mockBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $mockBreaker->shouldReceive('isOpen')->andReturn(true);
        $this->app->instance(CircuitBreakerInterface::class, $mockBreaker);

        // Lakukan 3 percobaan gagal
        for ($i = 0; $i < 3; $i++) {
            $this->post('/emergency/login', [
                'username' => 'wrong',
                'password' => 'wrong',
            ]);
        }

        // Login berhasil → rate limiter di-reset
        $this->post('/emergency/login', [
            'username' => 'emergency_admin',
            'password' => 'secret_pass_123',
        ]);

        $rateLimitKey = 'emergency_login:127.0.0.1';
        expect(RateLimiter::remaining($rateLimitKey, 5))->toBe(5);
    });

    test('log percobaan login yang berhasil ke database', function () {
        $mockBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $mockBreaker->shouldReceive('isOpen')->andReturn(true);
        $this->app->instance(CircuitBreakerInterface::class, $mockBreaker);

        $this->post('/emergency/login', [
            'username' => 'emergency_admin',
            'password' => 'secret_pass_123',
        ]);

        $log = KeycloakEmergencyLoginLog::latest()->first();
        expect($log)
            ->not->toBeNull()
            ->and($log->outcome)->toBe('success')
            ->and($log->ip_address)->toBe('127.0.0.1')
            ->and($log->username)->toBe(hash('sha256', 'emergency_admin'));
    });

    test('log percobaan login yang gagal ke database', function () {
        $mockBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $mockBreaker->shouldReceive('isOpen')->andReturn(true);
        $this->app->instance(CircuitBreakerInterface::class, $mockBreaker);

        $this->post('/emergency/login', [
            'username' => 'wrong_user',
            'password' => 'wrong_pass',
        ]);

        $log = KeycloakEmergencyLoginLog::latest()->first();
        expect($log)
            ->not->toBeNull()
            ->and($log->outcome)->toBe('failure')
            ->and($log->username)->toBe(hash('sha256', 'wrong_user'));
    });

    test('username disimpan sebagai hash di log', function () {
        $mockBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $mockBreaker->shouldReceive('isOpen')->andReturn(true);
        $this->app->instance(CircuitBreakerInterface::class, $mockBreaker);

        $this->post('/emergency/login', [
            'username' => 'emergency_admin',
            'password' => 'secret_pass_123',
        ]);

        $log = KeycloakEmergencyLoginLog::latest()->first();

        // Username harus di-hash, bukan plain text
        expect($log->username)
            ->not->toBe('emergency_admin')
            ->and($log->username)->toBe(hash('sha256', 'emergency_admin'));
    });

    test('user_agent dicatat dalam log', function () {
        $mockBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $mockBreaker->shouldReceive('isOpen')->andReturn(true);
        $this->app->instance(CircuitBreakerInterface::class, $mockBreaker);

        $this->withHeaders(['User-Agent' => 'TestBrowser/1.0'])
            ->post('/emergency/login', [
                'username' => 'emergency_admin',
                'password' => 'secret_pass_123',
            ]);

        $log = KeycloakEmergencyLoginLog::latest()->first();
        expect($log->user_agent)->toBe('TestBrowser/1.0');
    });

    test('validasi form menolak request tanpa username atau password', function () {
        $mockBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $mockBreaker->shouldReceive('isOpen')->andReturn(true);
        $this->app->instance(CircuitBreakerInterface::class, $mockBreaker);

        $response = $this->post('/emergency/login', []);

        $response->assertSessionHasErrors(['username', 'password']);
    });

    test('password diverifikasi menggunakan Hash::check (Req 10.5)', function () {
        // Pastikan password plaintext TIDAK cocok jika config berisi plaintext
        config()->set('keycloak.emergency.password', 'secret_pass_123'); // bukan hash

        $mockBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $mockBreaker->shouldReceive('isOpen')->andReturn(true);
        $this->app->instance(CircuitBreakerInterface::class, $mockBreaker);

        // Login harus gagal karena Hash::check('secret_pass_123', 'secret_pass_123') = false
        $response = $this->post('/emergency/login', [
            'username' => 'emergency_admin',
            'password' => 'secret_pass_123',
        ]);

        $response->assertRedirect(route('emergency.login.form'));
        $response->assertSessionHasErrors('credentials');
    });

    test('username menggunakan constant-time comparison (Req 10.5)', function () {
        $mockBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $mockBreaker->shouldReceive('isOpen')->andReturn(true);
        $this->app->instance(CircuitBreakerInterface::class, $mockBreaker);

        // Username salah → harus ditolak meskipun password benar
        $response = $this->post('/emergency/login', [
            'username' => 'emergency_admin_typo',
            'password' => 'secret_pass_123',
        ]);

        $response->assertRedirect(route('emergency.login.form'));
        $response->assertSessionHasErrors('credentials');
    });

    test('emergency session memiliki logged_in_at timestamp (Req 10.4)', function () {
        $mockBreaker = Mockery::mock(CircuitBreakerInterface::class);
        $mockBreaker->shouldReceive('isOpen')->andReturn(true);
        $this->app->instance(CircuitBreakerInterface::class, $mockBreaker);

        $this->freezeTime();

        $this->post('/emergency/login', [
            'username' => 'emergency_admin',
            'password' => 'secret_pass_123',
        ]);

        $emergencySession = session('keycloak.emergency');
        expect($emergencySession['logged_in_at']->toIso8601String())
            ->toBe(now()->toIso8601String());
    });
});
