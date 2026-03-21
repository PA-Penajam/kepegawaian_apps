<?php

use App\Http\Controllers\Api\IamController;
use App\Models\IamApplication;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

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

    Route::middleware(['auth:sanctum', 'iam.signature'])->group(function () {
        Route::post('/test-iam-logout', [IamController::class, 'logout']);
    });
});

test('logout endpoint menghapus token dan mengembalikan message', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $headers = makeIamHeaders('POST', '/test-iam-logout', $this->iamApp->api_key, 'test-secret-64chars-padding-here-abc123');

    $response = $this->postJson('/test-iam-logout', [], $headers);

    $response->assertOk()
        ->assertJsonPath('message', 'Token invalidated');
});

test('logout endpoint guest ditolak 401', function () {
    $this->postJson('/test-iam-logout')->assertUnauthorized();
});
