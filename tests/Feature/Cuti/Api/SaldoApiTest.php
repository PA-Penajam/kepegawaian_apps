<?php

use App\Models\Pegawai;
use App\Services\Cuti\SaldoLedgerService;
use Database\Seeders\CutiJenisMasterSeeder;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(CutiJenisMasterSeeder::class);
});

/**
 * Helper untuk membuat headers dengan HMAC signature untuk cuti saldo API.
 */
function saldoApiSignedHeaders(string $method, string $path, array $query = []): array
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

test('show returns saldo per jenis', function () {
    $pegawai = Pegawai::factory()->create();
    Sanctum::actingAs($pegawai, ['*']);

    // Siapkan alokasi saldo
    app(SaldoLedgerService::class)->kreditAlokasi($pegawai->nip, 'CT', 2026, 12, 'init');

    $path = "/api/cuti/saldo/{$pegawai->nip}";
    $headers = saldoApiSignedHeaders('GET', $path);
    $response = $this->getJson($path, $headers);

    $response->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'jenis_cuti_kode',
                    'tahun_hak',
                    'hak_awal',
                    'saldo_tersedia',
                ],
            ],
        ]);

    $firstItem = $response->json('data.0');
    expect($firstItem['jenis_cuti_kode'])->toBe('CT')
        ->and($firstItem['tahun_hak'])->toBe(2026)
        ->and($firstItem['hak_awal'])->toBe(12)
        ->and($firstItem['saldo_tersedia'])->toBe(12);
});

test('ledger returns paginated entries', function () {
    $pegawai = Pegawai::factory()->create();
    Sanctum::actingAs($pegawai, ['*']);

    // Siapkan alokasi + kredit saldo agar ada entry di ledger
    app(SaldoLedgerService::class)->kreditAlokasi($pegawai->nip, 'CT', 2026, 12, 'init');

    $path = "/api/cuti/saldo/{$pegawai->nip}/ledger";
    $headers = saldoApiSignedHeaders('GET', $path);
    $response = $this->getJson($path, $headers);

    $response->assertOk()
        ->assertJsonStructure([
            'data',
            'links',
            'meta',
        ]);

    expect($response->json('data'))->not->toBeEmpty();
});
