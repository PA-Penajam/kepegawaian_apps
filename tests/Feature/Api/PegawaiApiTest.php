<?php

use App\Models\User;
use Laravel\Sanctum\Sanctum;

/**
 * Helper untuk membuat headers dengan HMAC signature.
 */
function makeSignedHeaders(string $method, string $path, array $query = [], ?string $secret = null): array
{
    $secret ??= config('kepegawaian.secret_key');
    $timestamp = now()->timestamp;
    $queryString = http_build_query(collect($query)->sortKeys()->all());
    $payload = strtoupper($method) . ':' . $path . ':' . $queryString . ':' . $timestamp;
    $signature = hash_hmac('sha256', $payload, $secret);

    return [
        'X-Timestamp' => $timestamp,
        'X-Signature' => $signature,
        'Accept' => 'application/json',
    ];
}

test('request tanpa auth token ditolak 401', function () {
    $headers = makeSignedHeaders('GET', '/api/v1/pegawai/197501012005011001');
    $response = $this->getJson('/api/v1/pegawai/197501012005011001', $headers);
    $response->assertStatus(401);
});

test('request dengan signature salah ditolak 401', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $headers = makeSignedHeaders('GET', '/api/v1/pegawai/197501012005011001', [], 'wrong-secret');
    $response = $this->getJson('/api/v1/pegawai/197501012005011001', $headers);
    $response->assertStatus(401);
});

test('request dengan query string dimodifikasi setelah signing ditolak 401', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    // Sign dengan query yang benar
    $originalQuery = ['nip' => ['197501012005011001']];
    $headers = makeSignedHeaders('GET', '/api/v1/pegawai', $originalQuery);

    // Kirim dengan query yang berbeda (serangan tampering)
    $tamperedQuery = ['nip' => ['999999999999999999']];
    $response = $this->getJson('/api/v1/pegawai?' . http_build_query($tamperedQuery), $headers);
    $response->assertStatus(401);
});

test('request dengan timestamp kedaluwarsa ditolak 401', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $oldTimestamp = now()->subMinutes(6)->timestamp;
    $queryString = '';
    $payload = 'GET:/api/v1/pegawai/197501012005011001::' . $oldTimestamp;
    $signature = hash_hmac('sha256', $payload, config('kepegawaian.secret_key'));

    $response = $this->getJson('/api/v1/pegawai/197501012005011001', [
        'X-Timestamp' => $oldTimestamp,
        'X-Signature' => $signature,
        'Accept' => 'application/json',
    ]);
    $response->assertStatus(401);
});
