<?php

use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\IamRolePermission;
use App\Models\IamUserRole;
use App\Models\Pegawai;
use App\Models\SyncConsumer;
use App\Services\Cuti\SaldoLedgerService;
use App\Services\Iam\SyncConsumerCredentialService;
use Database\Seeders\CutiJenisMasterSeeder;
use Database\Seeders\CutiPermissionSeeder;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->seed(CutiJenisMasterSeeder::class);
    $this->seed(CutiPermissionSeeder::class);
});

/**
 * Helper untuk membuat headers dengan HMAC signature untuk cuti saldo API.
 */
function saldoApiSignedHeaders(string $method, string $path, array $query = [], ?string $secret = null, ?string $nonce = null): array
{
    $secret ??= config('kepegawaian.secret_key');
    $timestamp = now()->timestamp;
    $queryString = http_build_query(collect($query)->sortKeys()->all());
    $bodyContent = '[]';
    $bodyHash = hash('sha256', $bodyContent);
    $payload = strtoupper($method).':'.$path.':'.$queryString.':'.$bodyHash.':'.$timestamp;
    $signature = hash_hmac('sha256', $payload, $secret);

    $headers = [
        'X-Timestamp' => $timestamp,
        'X-Signature' => $signature,
        'Accept' => 'application/json',
    ];

    if ($nonce !== null) {
        $headers['X-Nonce'] = $nonce;
    }

    return $headers;
}

/**
 * Helper untuk memberi permission saldo cuti ke pegawai uji.
 *
 * @param  array<int, string>  $permissionSlugs
 */
function saldoApiGrantPermissions(Pegawai $pegawai, array $permissionSlugs): void
{
    $app = IamApplication::where('slug', 'kepegawaian')->firstOrFail();

    $role = IamRole::create([
        'iam_application_id' => $app->id,
        'slug' => 'api-saldo-'.$pegawai->nip.'-'.uniqid(),
        'nama' => 'API Saldo '.$pegawai->nip,
    ]);

    $permissionIds = IamPermission::where('iam_application_id', $app->id)
        ->whereIn('slug', $permissionSlugs)
        ->pluck('id');

    foreach ($permissionIds as $permId) {
        IamRolePermission::create([
            'iam_role_id' => $role->id,
            'iam_permission_id' => $permId,
        ]);
    }

    IamUserRole::create([
        'user_id' => $pegawai->id,
        'iam_role_id' => $role->id,
        'assigned_at' => now(),
    ]);
}

test('show returns saldo per jenis untuk pegawai sendiri berizin view-own', function () {
    $pegawai = Pegawai::factory()->create();
    saldoApiGrantPermissions($pegawai, ['cuti.saldo.view-own']);
    Sanctum::actingAs($pegawai, ['cuti:read', 'app:kepegawaian']);

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

test('ledger returns paginated entries untuk pegawai sendiri berizin view-own', function () {
    $pegawai = Pegawai::factory()->create();
    saldoApiGrantPermissions($pegawai, ['cuti.saldo.view-own']);
    Sanctum::actingAs($pegawai, ['cuti:read']);

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

test('saldo dan ledger menolak 403 akses IDOR ke NIP pegawai lain tanpa view-all', function () {
    $pemilik = Pegawai::factory()->create();
    $pengakses = Pegawai::factory()->create();
    saldoApiGrantPermissions($pengakses, ['cuti.saldo.view-own']);
    Sanctum::actingAs($pengakses, ['cuti:read']);

    app(SaldoLedgerService::class)->kreditAlokasi($pemilik->nip, 'CT', 2026, 12, 'init');

    $this->getJson("/api/cuti/saldo/{$pemilik->nip}", saldoApiSignedHeaders('GET', "/api/cuti/saldo/{$pemilik->nip}"))
        ->assertForbidden();

    $this->getJson("/api/cuti/saldo/{$pemilik->nip}/ledger", saldoApiSignedHeaders('GET', "/api/cuti/saldo/{$pemilik->nip}/ledger"))
        ->assertForbidden();
});

test('saldo dan ledger menolak 403 token tanpa ability cuti:read dan SyncConsumer', function () {
    $pegawai = Pegawai::factory()->create();
    saldoApiGrantPermissions($pegawai, ['cuti.saldo.view-all']);

    $path = "/api/cuti/saldo/{$pegawai->nip}";

    Sanctum::actingAs($pegawai, ['sync:pegawai']);
    $this->getJson($path, saldoApiSignedHeaders('GET', $path))->assertForbidden();

    Sanctum::actingAs($pegawai, ['app:kepegawaian']);
    $this->getJson($path, saldoApiSignedHeaders('GET', $path))->assertForbidden();

    $consumer = SyncConsumer::create([
        'nama' => 'Saldo Consumer',
        'slug' => 'saldo-consumer',
        'base_url' => 'https://saldo.example.test',
        'is_active' => true,
    ]);
    $consumerSecret = app(SyncConsumerCredentialService::class)->ensureHmacSecret($consumer);
    Sanctum::actingAs($consumer, ['cuti:read']);
    $this->getJson($path, saldoApiSignedHeaders('GET', $path, [], $consumerSecret, (string) Str::uuid()))->assertForbidden();
});

test('saldo dan ledger mengembalikan 404 untuk NIP non-18-digit', function () {
    $pegawai = Pegawai::factory()->create();
    saldoApiGrantPermissions($pegawai, ['cuti.saldo.view-all']);
    Sanctum::actingAs($pegawai, ['cuti:read']);

    $this->getJson('/api/cuti/saldo/12345', saldoApiSignedHeaders('GET', '/api/cuti/saldo/12345'))
        ->assertNotFound();

    $this->getJson('/api/cuti/saldo/abc123456789012345/ledger', saldoApiSignedHeaders('GET', '/api/cuti/saldo/abc123456789012345/ledger'))
        ->assertNotFound();
});
