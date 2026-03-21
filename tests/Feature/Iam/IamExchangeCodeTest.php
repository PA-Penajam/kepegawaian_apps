<?php

use App\Http\Controllers\Api\IamController;
use App\Models\IamApplication;
use App\Models\IamSsoCode;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;

// Helper untuk membuat IAM signed headers
if (! function_exists('makeIamHeaders')) {
    function makeIamHeaders(string $method, string $path, string $apiKey, string $apiSecret, array $query = []): array
{
    $timestamp   = now()->timestamp;
    $queryString = http_build_query(collect($query)->sortKeys()->all());
    $payload     = strtoupper($method) . ':' . $path . ':' . $queryString . ':' . $timestamp;
    $signature   = hash_hmac('sha256', $payload, $apiSecret);

    return [
        'X-App-Key'   => $apiKey,
        'X-Signature' => $signature,
        'X-Timestamp' => $timestamp,
        'Accept'      => 'application/json',
    ];
    }
}

beforeEach(function () {
    $this->iamApp = IamApplication::create([
        'nama'            => 'Test App',
        'slug'            => 'test-app',
        'url'             => 'http://test.local',
        'api_key'         => 'test-api-key',
        'api_secret_hash' => Crypt::encryptString('test-secret-64chars-padding-here-abc123'),
        'is_active'       => true,
        'is_system'       => false,
    ]);

    Route::middleware(['iam.signature'])->group(function () {
        Route::post('/test-iam-exchange', [IamController::class, 'exchangeCode']);
    });
});

test('exchange code valid mengembalikan token', function () {
    $user = User::factory()->create();
    $code = str_repeat('a', 64);

    IamSsoCode::create([
        'code'       => $code,
        'user_id'    => $user->id,
        'app_slug'   => 'test-app',
        'expires_at' => now()->addMinutes(5),
    ]);

    $headers = makeIamHeaders('POST', '/test-iam-exchange', $this->iamApp->api_key, 'test-secret-64chars-padding-here-abc123');

    $response = $this->postJson('/test-iam-exchange', ['code' => $code], $headers);

    $response->assertOk()
        ->assertJsonStructure([
            'token',
            'token_type',
            'expires_at',
        ])
        ->assertJsonPath('token_type', 'Bearer');
});

test('exchange code invalid/expired mengembalikan 400', function () {
    $headers = makeIamHeaders('POST', '/test-iam-exchange', $this->iamApp->api_key, 'test-secret-64chars-padding-here-abc123');

    $response = $this->postJson('/test-iam-exchange', ['code' => str_repeat('b', 64)], $headers);

    $response->assertBadRequest()
        ->assertJsonPath('message', 'Invalid or expired code');
});

test('exchange code sudah dipakai mengembalikan 400', function () {
    $user = User::factory()->create();
    $code = str_repeat('c', 64);

    IamSsoCode::create([
        'code'       => $code,
        'user_id'    => $user->id,
        'app_slug'   => 'test-app',
        'expires_at' => now()->addMinutes(5),
        'used_at'    => now(), // sudah dipakai
    ]);

    $headers = makeIamHeaders('POST', '/test-iam-exchange', $this->iamApp->api_key, 'test-secret-64chars-padding-here-abc123');

    $response = $this->postJson('/test-iam-exchange', ['code' => $code], $headers);

    $response->assertBadRequest()
        ->assertJsonPath('message', 'Invalid or expired code');
});
