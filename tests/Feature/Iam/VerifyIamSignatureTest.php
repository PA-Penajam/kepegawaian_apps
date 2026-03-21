<?php

use App\Models\IamApplication;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

// Helper untuk membuat IAM signed headers
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

beforeEach(function () {
    Route::middleware(['auth:sanctum', 'iam.signature'])
        ->get('/test-iam-signature', fn () => response()->json(['ok' => true]));
});

test('request tanpa X-App-Key ditolak 401', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/test-iam-signature')->assertStatus(401);
});

test('request dengan api_key tidak dikenal ditolak 401', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson('/test-iam-signature', [
        'X-App-Key'   => 'unknown-key',
        'X-Signature' => 'anything',
        'X-Timestamp' => now()->timestamp,
    ])->assertStatus(401);
});

test('request dengan signature salah ditolak 401', function () {
    $user    = User::factory()->create();
    $secret  = 'correct-secret-64chars-padding-here-abc123def456ghi789';
    $app     = IamApplication::create([
        'nama'            => 'Test App',
        'slug'            => 'test',
        'url'             => 'http://test.local',
        'api_key'         => 'valid-key-123',
        'api_secret_hash' => Crypt::encryptString($secret),
    ]);

    Sanctum::actingAs($user);

    $headers = makeIamHeaders('GET', '/test-iam-signature', $app->api_key, 'wrong-secret');
    $this->getJson('/test-iam-signature', $headers)->assertStatus(401);
});

test('request dengan timestamp kedaluwarsa ditolak 401', function () {
    $user    = User::factory()->create();
    $secret  = 'correct-secret-64chars-padding-here-abc123def456ghi789';
    $app     = IamApplication::create([
        'nama'            => 'Test App',
        'slug'            => 'test',
        'url'             => 'http://test.local',
        'api_key'         => 'valid-key-123',
        'api_secret_hash' => Crypt::encryptString($secret),
    ]);

    Sanctum::actingAs($user);

    $oldTimestamp = now()->subMinutes(6)->timestamp;
    $payload      = 'GET:/test-iam-signature::' . $oldTimestamp;
    $signature    = hash_hmac('sha256', $payload, $secret);

    $this->getJson('/test-iam-signature', [
        'X-App-Key'   => $app->api_key,
        'X-Signature' => $signature,
        'X-Timestamp' => $oldTimestamp,
    ])->assertStatus(401);
});

test('request valid dengan signature benar lolos', function () {
    $user    = User::factory()->create();
    $secret  = 'correct-secret-64chars-padding-here-abc123def456ghi789';
    $app     = IamApplication::create([
        'nama'            => 'Test App',
        'slug'            => 'test',
        'url'             => 'http://test.local',
        'api_key'         => 'valid-key-123',
        'api_secret_hash' => Crypt::encryptString($secret),
    ]);

    Sanctum::actingAs($user);

    $headers = makeIamHeaders('GET', '/test-iam-signature', $app->api_key, $secret);
    $this->getJson('/test-iam-signature', $headers)->assertOk();
});
