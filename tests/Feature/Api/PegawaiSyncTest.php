<?php

use App\Models\Pegawai;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Sanctum\Sanctum;

/**
 * Helper untuk membuat headers HMAC pada endpoint sync.
 *
 * Disengaja memakai nama berbeda dari makeSignedHeaders() di PegawaiApiTest
 * agar tidak terjadi redeclare fungsi global antar file test.
 */
function makeSyncSignedHeaders(string $method, string $path, array $query = [], ?string $secret = null): array
{
    $secret ??= config('kepegawaian.secret_key');
    $timestamp = now()->timestamp;
    $queryString = http_build_query(collect($query)->sortKeys()->all());
    // Laravel getJson mengirim '[]' sebagai body default, bukan ''
    $bodyHash = hash('sha256', '[]');
    $payload = strtoupper($method).':'.$path.':'.$queryString.':'.$bodyHash.':'.$timestamp;
    $signature = hash_hmac('sha256', $payload, $secret);

    return [
        'X-Timestamp' => $timestamp,
        'X-Signature' => $signature,
        'Accept' => 'application/json',
    ];
}

/**
 * Simulasi loop getAll() milik wfa-task: telusuri halaman sampai berhenti.
 *
 * @return array{items: array<int, array<string, mixed>>, iterations: int}
 */
function walkSyncPages(object $testCase, array $baseQuery, int $perPage): array
{
    $items = [];
    $page = 1;
    $previousPage = 0;
    $iterations = 0;

    for ($i = 0; $i < 200; $i++) {
        $query = array_merge($baseQuery, ['page' => $page, 'per_page' => $perPage]);
        $headers = makeSyncSignedHeaders('GET', '/api/v1/pegawai/sync', $query);
        $response = $testCase->getJson('/api/v1/pegawai/sync?'.http_build_query($query), $headers);

        $response->assertStatus(200);

        $body = $response->json();
        $data = $body['data'] ?? [];

        foreach ($data as $item) {
            $items[] = $item;
        }

        $meta = $body['meta'] ?? [];
        $lastPage = $meta['last_page'] ?? null;
        $currentPage = $meta['current_page'] ?? $page;
        $iterations++;

        if ($data === [] || ($lastPage !== null && $currentPage >= $lastPage)) {
            break;
        }

        if ($currentPage <= $previousPage) {
            break;
        }

        $previousPage = $currentPage;
        $page = $currentPage + 1;
    }

    return ['items' => $items, 'iterations' => $iterations];
}

// Bersihkan rate limiter agar throttle tidak mengganggu antar test
beforeEach(function () {
    RateLimiter::clear('127.0.0.1|api/v1/pegawai/sync');
});

test('sync tanpa auth token ditolak 401', function () {
    $headers = makeSyncSignedHeaders('GET', '/api/v1/pegawai/sync');
    $response = $this->getJson('/api/v1/pegawai/sync', $headers);
    $response->assertStatus(401);
});

test('sync dengan signature salah ditolak 401', function () {
    $user = Pegawai::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $headers = makeSyncSignedHeaders('GET', '/api/v1/pegawai/sync', [], 'wrong-secret');
    $response = $this->getJson('/api/v1/pegawai/sync', $headers);
    $response->assertStatus(401);
});

test('sync mengembalikan meta paginator lengkap', function () {
    $user = Pegawai::factory()->create();
    Pegawai::factory()->count(24)->create();
    Sanctum::actingAs($user, ['*']);

    $query = ['page' => 1, 'per_page' => 10];
    $headers = makeSyncSignedHeaders('GET', '/api/v1/pegawai/sync', $query);
    $response = $this->getJson('/api/v1/pegawai/sync?'.http_build_query($query), $headers);

    $response->assertStatus(200)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.last_page', 3)
        ->assertJsonPath('meta.per_page', 10)
        ->assertJsonPath('meta.total', 25)
        ->assertJsonStructure(['data', 'meta', 'synced_at']);
});

test('setiap item sync memenuhi kontrak field wfa-task', function () {
    $user = Pegawai::factory()->create();
    $pegawai = Pegawai::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $query = ['page' => 1, 'per_page' => 100];
    $headers = makeSyncSignedHeaders('GET', '/api/v1/pegawai/sync', $query);
    $response = $this->getJson('/api/v1/pegawai/sync?'.http_build_query($query), $headers);

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [['nip', 'nama', 'jabatan', 'email', 'unit_kerja', 'status_pegawai']],
        ]);

    $item = collect($response->json('data'))->firstWhere('nip', $pegawai->nip);

    expect($item)->not->toBeNull()
        ->and($item['nama'])->toBe($pegawai->nama_lengkap)
        ->and($item['status_pegawai'])->toBe($pegawai->status_pegawai->value);
});

test('walk paginasi ala wfa-task berhenti tepat dan lengkap tanpa duplikat', function () {
    $user = Pegawai::factory()->create();
    $nips = Pegawai::factory()->count(25)->create()
        ->pluck('nip')->push($user->nip)->sort()->values()->all();
    Sanctum::actingAs($user, ['*']);

    $walk = walkSyncPages($this, [], 10);

    // Berhenti tepat di halaman terakhir: tidak ada request halaman kosong tambahan
    expect($walk['iterations'])->toBe(3);

    $walkedNips = collect($walk['items'])->pluck('nip')->all();
    sort($walkedNips);

    expect($walkedNips)->toBe($nips);
});

test('filter since hanya mengembalikan data yang berubah', function () {
    $user = Pegawai::factory()->create();
    $lama = Pegawai::factory()->create(['updated_at' => now()->subDays(10)]);
    $baru = Pegawai::factory()->create(['updated_at' => now()]);
    Sanctum::actingAs($user, ['*']);

    $since = now()->subDay()->toIso8601String();
    $query = ['since' => $since, 'page' => 1, 'per_page' => 100];
    $headers = makeSyncSignedHeaders('GET', '/api/v1/pegawai/sync', $query);
    $response = $this->getJson('/api/v1/pegawai/sync?'.http_build_query($query), $headers);

    $response->assertStatus(200);

    $nips = collect($response->json('data'))->pluck('nip')->all();

    expect($nips)->toContain($baru->nip, $user->nip)
        ->and($nips)->not->toContain($lama->nip);
});

test('pegawai yang di-soft-delete tidak ikut ekspor sync', function () {
    $user = Pegawai::factory()->create();
    $hapus = Pegawai::factory()->create();
    $hapus->delete();
    Sanctum::actingAs($user, ['*']);

    $query = ['page' => 1, 'per_page' => 100];
    $headers = makeSyncSignedHeaders('GET', '/api/v1/pegawai/sync', $query);
    $response = $this->getJson('/api/v1/pegawai/sync?'.http_build_query($query), $headers);

    $response->assertStatus(200);

    $nips = collect($response->json('data'))->pluck('nip')->all();

    expect($nips)->not->toContain($hapus->nip)
        ->and($response->json('meta.total'))->toBe(1);
});

test('parameter sync yang tidak valid ditolak 422', function () {
    $user = Pegawai::factory()->create();
    Sanctum::actingAs($user, ['*']);

    $query = ['per_page' => 9999];
    $headers = makeSyncSignedHeaders('GET', '/api/v1/pegawai/sync', $query);
    $this->getJson('/api/v1/pegawai/sync?'.http_build_query($query), $headers)
        ->assertStatus(422);

    $query = ['since' => 'bukan-tanggal'];
    $headers = makeSyncSignedHeaders('GET', '/api/v1/pegawai/sync', $query);
    $this->getJson('/api/v1/pegawai/sync?'.http_build_query($query), $headers)
        ->assertStatus(422);
});

test('search pegawai lama kini juga memuat meta paginator lengkap', function () {
    $user = Pegawai::factory()->create();
    Pegawai::factory()->count(4)->create();
    Sanctum::actingAs($user, ['*']);

    $query = ['page' => 1];
    $headers = makeSyncSignedHeaders('GET', '/api/v1/pegawai', $query);
    $response = $this->getJson('/api/v1/pegawai?'.http_build_query($query), $headers);

    $response->assertStatus(200)
        ->assertJsonPath('meta.current_page', 1)
        ->assertJsonPath('meta.last_page', 1)
        ->assertJsonPath('meta.total', 5);
});
