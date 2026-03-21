<?php

use App\Enums\StatusPegawai;
use App\Models\Pegawai;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;

/**
 * Helper untuk membuat headers dengan HMAC signature.
 */
function makeSignedHeaders(string $method, string $path, array $query = [], ?string $secret = null, ?string $body = null): array
{
    $secret ??= config('kepegawaian.secret_key');
    $timestamp = now()->timestamp;
    $queryString = http_build_query(collect($query)->sortKeys()->all());
    // Laravel getJson/postJson mengirim '[]' sebagai body default, bukan ''
    $bodyContent = $body ?? '[]';
    $bodyHash = hash('sha256', $bodyContent);
    $payload = strtoupper($method).':'.$path.':'.$queryString.':'.$bodyHash.':'.$timestamp;
    $signature = hash_hmac('sha256', $payload, $secret);

    return [
        'X-Timestamp' => $timestamp,
        'X-Signature' => $signature,
        'Accept' => 'application/json',
    ];
}

// Bersihkan rate limiter sebelum setiap test untuk menghindari throttling di test environment
beforeEach(function () {
    // Throttle menggunakan IP atau user ID sebagai key
    // Di test environment, kita bersihkan untuk IP 127.0.0.1
    RateLimiter::clear('127.0.0.1|api/v1/pegawai');
    RateLimiter::clear('127.0.0.1|api/v1/pegawai/*');
});

test('request tanpa auth token ditolak 401', function () {
    $headers = makeSignedHeaders('GET', '/api/v1/pegawai/197501012005011001');
    $response = $this->getJson('/api/v1/pegawai/197501012005011001', $headers);
    $response->assertStatus(401);
});

test('request dengan signature salah ditolak 401', function () {
    $user = Pegawai::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $headers = makeSignedHeaders('GET', '/api/v1/pegawai/197501012005011001', [], 'wrong-secret');
    $response = $this->getJson('/api/v1/pegawai/197501012005011001', $headers);
    $response->assertStatus(401);
});

test('request dengan query string dimodifikasi setelah signing ditolak 401', function () {
    $user = Pegawai::factory()->create();
    Sanctum::actingAs($user, ['*']);

    // Sign dengan query yang benar
    $originalQuery = ['nip' => ['197501012005011001']];
    $headers = makeSignedHeaders('GET', '/api/v1/pegawai', $originalQuery);

    // Kirim dengan query yang berbeda (serangan tampering)
    $tamperedQuery = ['nip' => ['999999999999999999']];
    $response = $this->getJson('/api/v1/pegawai?'.http_build_query($tamperedQuery), $headers);
    $response->assertStatus(401);
});

test('request dengan timestamp kedaluwarsa ditolak 401', function () {
    $user = Pegawai::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $oldTimestamp = now()->subMinutes(6)->timestamp;
    $queryString = '';
    $payload = 'GET:/api/v1/pegawai/197501012005011001::'.$oldTimestamp;
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
    $user = Pegawai::factory()->create();
    $pegawai = Pegawai::factory()->create();

    Sanctum::actingAs($user, ['*']);
    $headers = makeSignedHeaders('GET', '/api/v1/pegawai/'.$pegawai->nip);

    $response = $this->getJson('/api/v1/pegawai/'.$pegawai->nip, $headers);

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
    $user = Pegawai::factory()->create();
    Sanctum::actingAs($user, ['*']);
    $headers = makeSignedHeaders('GET', '/api/v1/pegawai/000000000000000000');

    $response = $this->getJson('/api/v1/pegawai/000000000000000000', $headers);
    $response->assertStatus(404)
        ->assertJsonPath('message', 'Pegawai tidak ditemukan')
        ->assertJsonPath('errors.nip.0', 'NIP tidak terdaftar');
});

test('GET pegawai batch mengembalikan data dan not_found', function () {
    $user = Pegawai::factory()->create();
    $pegawai = Pegawai::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $query = ['nip' => [$pegawai->nip, '000000000000000001']];
    $headers = makeSignedHeaders('GET', '/api/v1/pegawai', $query);

    $response = $this->getJson('/api/v1/pegawai?'.http_build_query($query), $headers);
    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('not_found.0', '000000000000000001');
});

test('GET pegawai batch > 50 NIP mengembalikan 422', function () {
    $user = Pegawai::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $nips = array_fill(0, 51, '197501012005011001');
    $query = ['nip' => $nips];
    $headers = makeSignedHeaders('GET', '/api/v1/pegawai', $query);

    $response = $this->getJson('/api/v1/pegawai?'.http_build_query($query), $headers);
    $response->assertStatus(422);
});

test('GET pegawai search mengembalikan hanya pegawai aktif', function () {
    $user = Pegawai::factory()->create();
    $aktif = Pegawai::factory()->create(['status_pegawai' => StatusPegawai::Aktif, 'nama_lengkap' => 'Budi Aktif']);
    $pensiun = Pegawai::factory()->create(['status_pegawai' => StatusPegawai::Pensiun, 'nama_lengkap' => 'Budi Pensiun']);
    Sanctum::actingAs($user, ['*']);

    $query = ['search' => 'Budi', 'status' => 'aktif'];
    $headers = makeSignedHeaders('GET', '/api/v1/pegawai', $query);

    $response = $this->getJson('/api/v1/pegawai?'.http_build_query($query), $headers);
    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nama', 'Budi Aktif');
});

test('parameter nip[] diprioritaskan jika search juga dikirim', function () {
    $user = Pegawai::factory()->create();
    $pegawai = Pegawai::factory()->create(['nama_lengkap' => 'Target Pegawai']);
    Sanctum::actingAs($user, ['*']);

    $query = ['nip' => [$pegawai->nip], 'search' => 'lainnya'];
    $headers = makeSignedHeaders('GET', '/api/v1/pegawai', $query);

    $response = $this->getJson('/api/v1/pegawai?'.http_build_query($query), $headers);
    $response->assertStatus(200)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.nama', 'Target Pegawai');
});

// =============================================================================
// Task 11: Validasi Input nip[] Format NIP
// =============================================================================

test('menolak nip dengan format tidak valid dalam batch request', function () {
    $user = Pegawai::factory()->create();
    Sanctum::actingAs($user, ['*']);

    // Kirim NIP dengan format yang tidak valid (bukan 18 digit)
    $query = ['nip' => ['BUKAN18DIGIT', '12345']];
    $headers = makeSignedHeaders('GET', '/api/v1/pegawai', $query);

    $response = $this->getJson('/api/v1/pegawai?'.http_build_query($query), $headers);
    $response->assertStatus(422)
        ->assertJsonValidationErrors(['nip.0', 'nip.1']);
});

test('menolak nip dengan kurang dari 18 digit', function () {
    $user = Pegawai::factory()->create();
    Sanctum::actingAs($user, ['*']);

    // Kirim NIP dengan 17 digit (kurang dari 18)
    $query = ['nip' => ['12345678901234567']];
    $headers = makeSignedHeaders('GET', '/api/v1/pegawai', $query);

    $response = $this->getJson('/api/v1/pegawai?'.http_build_query($query), $headers);
    $response->assertStatus(422)
        ->assertJsonPath('message', 'The nip.0 field must be 18 digits.');
});

test('menolak nip dengan lebih dari 18 digit', function () {
    $user = Pegawai::factory()->create();
    Sanctum::actingAs($user, ['*']);

    // Kirim NIP dengan 19 digit (lebih dari 18)
    $query = ['nip' => ['1234567890123456789']];
    $headers = makeSignedHeaders('GET', '/api/v1/pegawai', $query);

    $response = $this->getJson('/api/v1/pegawai?'.http_build_query($query), $headers);
    $response->assertStatus(422)
        ->assertJsonPath('message', 'The nip.0 field must be 18 digits.');
});

test('menolak batch request lebih dari 50 nip', function () {
    $user = Pegawai::factory()->create();
    Sanctum::actingAs($user, ['*']);

    // Buat 51 NIP valid (18 digit)
    $nips = array_fill(0, 51, str_repeat('1', 18));
    $query = ['nip' => $nips];
    $headers = makeSignedHeaders('GET', '/api/v1/pegawai', $query);

    $response = $this->getJson('/api/v1/pegawai?'.http_build_query($query), $headers);
    $response->assertStatus(422)
        ->assertJsonPath('message', 'The nip field must not have more than 50 items.');
});
