<?php

use App\Http\Controllers\Api\IamController;
use App\Models\IamApplication;
use App\Models\IamSsoCode;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->iamApp = IamApplication::factory()->create(['is_active' => true]);

    Route::middleware(['iam.signature', 'throttle:10,1'])->group(function () {
        Route::post('/test-iam-exchange', [IamController::class, 'exchangeCode']);
    });

    // Bersihkan rate limiter sebelum setiap test untuk menghindari cross-test interference
    // Laravel throttle menggunakan IP atau user ID sebagai key, di test environment gunakan IP 127.0.0.1
    RateLimiter::clear('127.0.0.1|test-iam-exchange');
});

test('exchange code valid mengembalikan token', function () {
    $user = User::factory()->create();
    $code = str_repeat('a', 64);

    IamSsoCode::create([
        'code' => $code,
        'user_id' => $user->id,
        'app_slug' => $this->iamApp->slug,
        'expires_at' => now()->addMinutes(5),
    ]);

    // Helper baru membuat app sendiri, jadi kita pakai app yang sudah dibuat di beforeEach
    $ts = now()->timestamp;
    $body = ['code' => $code];
    $bodyHash = hash('sha256', json_encode($body));
    $payload = 'POST:/test-iam-exchange::'.$bodyHash.':'.$ts;
    $signature = hash_hmac('sha256', $payload, Crypt::decryptString($this->iamApp->api_secret_hash));

    $response = $this->postJson('/test-iam-exchange', $body, [
        'X-App-Key' => $this->iamApp->api_key,
        'X-Signature' => $signature,
        'X-Timestamp' => $ts,
        'Accept' => 'application/json',
    ]);

    $response->assertOk()
        ->assertJsonStructure([
            'token',
            'token_type',
            'expires_at',
        ])
        ->assertJsonPath('token_type', 'Bearer');
});

test('exchange code invalid/expired mengembalikan 400', function () {
    $ts = now()->timestamp;
    $body = ['code' => str_repeat('b', 64)];
    $bodyHash = hash('sha256', json_encode($body));
    $payload = 'POST:/test-iam-exchange::'.$bodyHash.':'.$ts;
    $signature = hash_hmac('sha256', $payload, Crypt::decryptString($this->iamApp->api_secret_hash));

    $response = $this->postJson('/test-iam-exchange', $body, [
        'X-App-Key' => $this->iamApp->api_key,
        'X-Signature' => $signature,
        'X-Timestamp' => $ts,
        'Accept' => 'application/json',
    ]);

    $response->assertBadRequest()
        ->assertJsonPath('message', 'Invalid or expired code');
});

test('exchange code sudah dipakai mengembalikan 400', function () {
    $user = User::factory()->create();
    $code = str_repeat('c', 64);

    IamSsoCode::create([
        'code' => $code,
        'user_id' => $user->id,
        'app_slug' => $this->iamApp->slug,
        'expires_at' => now()->addMinutes(5),
        'used_at' => now(), // sudah dipakai
    ]);

    $ts = now()->timestamp;
    $body = ['code' => $code];
    $bodyHash = hash('sha256', json_encode($body));
    $payload = 'POST:/test-iam-exchange::'.$bodyHash.':'.$ts;
    $signature = hash_hmac('sha256', $payload, Crypt::decryptString($this->iamApp->api_secret_hash));

    $response = $this->postJson('/test-iam-exchange', $body, [
        'X-App-Key' => $this->iamApp->api_key,
        'X-Signature' => $signature,
        'X-Timestamp' => $ts,
        'Accept' => 'application/json',
    ]);

    $response->assertBadRequest()
        ->assertJsonPath('message', 'Invalid or expired code');
});

test('menolak request ke-11 pada exchange-code dengan HTTP 429', function () {
    // Kirim 10 request valid (semua akan gagal dengan 400 karena code tidak ada, tapi tidak ter-throttle)
    for ($i = 0; $i < 10; $i++) {
        $ts = now()->timestamp;
        $body = ['code' => str_repeat('a', 64)];
        $bodyHash = hash('sha256', json_encode($body));
        $payload = 'POST:/test-iam-exchange::'.$bodyHash.':'.$ts;
        $signature = hash_hmac('sha256', $payload, Crypt::decryptString($this->iamApp->api_secret_hash));

        $this->postJson('/test-iam-exchange', $body, [
            'X-App-Key' => $this->iamApp->api_key,
            'X-Signature' => $signature,
            'X-Timestamp' => $ts,
            'Accept' => 'application/json',
        ]);
    }

    // Request ke-11 harus mendapat 429 Too Many Requests
    $ts = now()->timestamp;
    $body = ['code' => str_repeat('a', 64)];
    $bodyHash = hash('sha256', json_encode($body));
    $payload = 'POST:/test-iam-exchange::'.$bodyHash.':'.$ts;
    $signature = hash_hmac('sha256', $payload, Crypt::decryptString($this->iamApp->api_secret_hash));

    $response = $this->postJson('/test-iam-exchange', $body, [
        'X-App-Key' => $this->iamApp->api_key,
        'X-Signature' => $signature,
        'X-Timestamp' => $ts,
        'Accept' => 'application/json',
    ]);

    $response->assertStatus(429);
});
