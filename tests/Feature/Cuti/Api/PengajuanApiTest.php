<?php

use App\Models\Cuti\CutiPengajuan;
use App\Models\Pegawai;
use App\Services\Cuti\SaldoLedgerService;
use Database\Seeders\CutiJenisMasterSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(CutiJenisMasterSeeder::class);
});

/**
 * Helper untuk membuat headers dengan HMAC signature untuk cuti API.
 */
function cutiApiSignedHeaders(string $method, string $path, array $query = []): array
{
    $secret = config('kepegawaian.secret_key');
    $timestamp = now()->timestamp;
    $queryString = http_build_query(collect($query)->sortKeys()->all());
    $bodyContent = '[]';
    $bodyHash = hash('sha256', $bodyContent);
    $payload = strtoupper($method).':'.$path.':'.$queryString.':'.$bodyHash.':'.$timestamp;
    $signature = hash_hmac('sha256', $payload, $secret);

    return [
        'X-Timestamp' => $timestamp,
        'X-Signature' => $signature,
        'Accept' => 'application/json',
    ];
}

test('index returns paginated pengajuan', function () {
    $pegawai = Pegawai::factory()->create();
    Sanctum::actingAs($pegawai, ['*']);

    // Siapkan alokasi dan submit pengajuan
    app(SaldoLedgerService::class)->kreditAlokasi($pegawai->nip, 'CT', 2026, 12, 'init');

    CutiPengajuan::factory()
        ->count(3)
        ->create([
            'pegawai_nip' => $pegawai->nip,
            'jenis_cuti_kode' => 'CT',
        ]);

    $headers = cutiApiSignedHeaders('GET', '/api/cuti/pengajuan');
    $response = $this->getJson('/api/cuti/pengajuan', $headers);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'nomor_pengajuan',
                    'pegawai_nip',
                    'jenis_cuti_kode',
                    'tanggal_mulai',
                    'tanggal_selesai',
                    'jumlah_hari_kerja',
                    'alasan',
                    'state',
                ],
            ],
            'links',
            'meta',
        ]);

    expect($response->json('data'))->toHaveCount(3);
});

test('show returns pengajuan with relations', function () {
    $pegawai = Pegawai::factory()->create();
    Sanctum::actingAs($pegawai, ['*']);

    $pengajuan = CutiPengajuan::factory()->create([
        'pegawai_nip' => $pegawai->nip,
        'jenis_cuti_kode' => 'CT',
    ]);

    $headers = cutiApiSignedHeaders('GET', "/api/cuti/pengajuan/{$pengajuan->id}");
    $response = $this->getJson("/api/cuti/pengajuan/{$pengajuan->id}", $headers);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                'id',
                'nomor_pengajuan',
                'pegawai_nip',
                'jenis_cuti_kode',
                'state',
                'pegawai' => ['nip', 'nama'],
                'jenis_cuti' => ['kode', 'nama'],
            ],
        ]);

    expect($response->json('data.id'))->toBe($pengajuan->id);
});

test('unauthenticated returns 401', function () {
    $response = $this->getJson('/api/cuti/pengajuan');

    $response->assertUnauthorized();
});
