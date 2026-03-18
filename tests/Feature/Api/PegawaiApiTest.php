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

// =============================================================================
// Task 3: PegawaiApiResource Tests
// =============================================================================

test('response single pegawai memiliki field yang benar', function () {
    $user    = User::factory()->create();
    $pegawai = \App\Models\Pegawai::factory()->create();

    Sanctum::actingAs($user, ['*']);
    $headers = makeSignedHeaders('GET', '/api/v1/pegawai/' . $pegawai->nip);

    $response = $this->getJson('/api/v1/pegawai/' . $pegawai->nip, $headers);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => ['nip', 'nama', 'jabatan', 'unit_kerja', 'status_pegawai', 'foto_url'],
        ])
        ->assertJsonPath('data.nip', $pegawai->nip)
        ->assertJsonPath('data.nama', $pegawai->nama_lengkap);  // nama_lengkap di-map ke nama
});

// =============================================================================
// Task 4: PegawaiApiController Tests
// =============================================================================

test('GET pegawai/{nip} 404 jika NIP tidak ditemukan', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);
    $headers = makeSignedHeaders('GET', '/api/v1/pegawai/000000000000000000');

    $response = $this->getJson('/api/v1/pegawai/000000000000000000', $headers);
    $response->assertStatus(404)
        ->assertJsonPath('message', 'Pegawai tidak ditemukan')
        ->assertJsonPath('errors.nip.0', 'NIP tidak terdaftar');
});

test('GET pegawai batch mengembalikan data dan not_found', function () {
    $user    = User::factory()->create();
    $pegawai = \App\Models\Pegawai::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $query   = ['nip' => [$pegawai->nip, '000000000000000001']];
    $headers = makeSignedHeaders('GET', '/api/v1/pegawai', $query);

    $response = $this->getJson('/api/v1/pegawai?' . http_build_query($query), $headers);
    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('not_found.0', '000000000000000001');
});

test('GET pegawai batch > 50 NIP mengembalikan 422', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $nips    = array_fill(0, 51, '197501012005011001');
    $query   = ['nip' => $nips];
    $headers = makeSignedHeaders('GET', '/api/v1/pegawai', $query);

    $response = $this->getJson('/api/v1/pegawai?' . http_build_query($query), $headers);
    $response->assertStatus(422);
});

test('GET pegawai search mengembalikan hanya pegawai aktif', function () {
    $user    = User::factory()->create();
    $aktif   = \App\Models\Pegawai::factory()->create(['status_pegawai' => \App\Enums\StatusPegawai::Aktif, 'nama_lengkap' => 'Budi Aktif']);
    $pensiun = \App\Models\Pegawai::factory()->create(['status_pegawai' => \App\Enums\StatusPegawai::Pensiun, 'nama_lengkap' => 'Budi Pensiun']);
    Sanctum::actingAs($user, ['*']);

    $query   = ['search' => 'Budi', 'status' => 'aktif'];
    $headers = makeSignedHeaders('GET', '/api/v1/pegawai', $query);

    $response = $this->getJson('/api/v1/pegawai?' . http_build_query($query), $headers);
    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nama', 'Budi Aktif');
});

test('parameter nip[] diprioritaskan jika search juga dikirim', function () {
    $user    = User::factory()->create();
    $pegawai = \App\Models\Pegawai::factory()->create(['nama_lengkap' => 'Target Pegawai']);
    Sanctum::actingAs($user, ['*']);

    $query   = ['nip' => [$pegawai->nip], 'search' => 'lainnya'];
    $headers = makeSignedHeaders('GET', '/api/v1/pegawai', $query);

    $response = $this->getJson('/api/v1/pegawai?' . http_build_query($query), $headers);
    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nama', 'Target Pegawai');
});
