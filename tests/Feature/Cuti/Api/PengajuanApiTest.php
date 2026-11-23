<?php

use App\Models\Cuti\CutiPengajuan;
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
 * Helper untuk membuat headers dengan HMAC signature untuk cuti API.
 */
function cutiApiSignedHeaders(string $method, string $path, array $query = [], ?string $secret = null, ?string $nonce = null): array
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
 * Helper untuk memberi permission cuti ke pegawai uji.
 *
 * @param  array<int, string>  $permissionSlugs
 */
function cutiApiGrantPermissions(Pegawai $pegawai, array $permissionSlugs): void
{
    $app = IamApplication::where('slug', 'kepegawaian')->firstOrFail();

    $role = IamRole::create([
        'iam_application_id' => $app->id,
        'slug' => 'api-cuti-'.$pegawai->nip.'-'.uniqid(),
        'nama' => 'API Cuti '.$pegawai->nip,
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

test('index returns paginated pengajuan milik sendiri untuk pegawai berizin view-own', function () {
    $pegawai = Pegawai::factory()->create();
    $pegawaiLain = Pegawai::factory()->create();
    cutiApiGrantPermissions($pegawai, ['cuti.pengajuan.view-own']);
    Sanctum::actingAs($pegawai, ['cuti:read', 'app:kepegawaian']);

    // Siapkan alokasi dan submit pengajuan
    app(SaldoLedgerService::class)->kreditAlokasi($pegawai->nip, 'CT', 2026, 12, 'init');

    CutiPengajuan::factory()
        ->count(3)
        ->create([
            'pegawai_nip' => $pegawai->nip,
            'jenis_cuti_kode' => 'CT',
        ]);

    // Pengajuan milik pegawai lain tidak boleh muncul
    CutiPengajuan::factory()->create([
        'pegawai_nip' => $pegawaiLain->nip,
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

test('show returns pengajuan with relations untuk pemilik berizin view-own', function () {
    $pegawai = Pegawai::factory()->create();
    cutiApiGrantPermissions($pegawai, ['cuti.pengajuan.view-own']);
    Sanctum::actingAs($pegawai, ['cuti:read']);

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

test('show menolak 403 akses IDOR ke pengajuan milik pegawai lain', function () {
    $pemilik = Pegawai::factory()->create();
    $pengakses = Pegawai::factory()->create();
    cutiApiGrantPermissions($pengakses, ['cuti.pengajuan.view-own']);
    Sanctum::actingAs($pengakses, ['cuti:read']);

    $pengajuan = CutiPengajuan::factory()->create([
        'pegawai_nip' => $pemilik->nip,
        'jenis_cuti_kode' => 'CT',
    ]);

    $headers = cutiApiSignedHeaders('GET', "/api/cuti/pengajuan/{$pengajuan->id}");
    $this->getJson("/api/cuti/pengajuan/{$pengajuan->id}", $headers)->assertForbidden();
});

test('index dan show menolak 403 pegawai tanpa permission cuti', function () {
    $pegawai = Pegawai::factory()->create();
    Sanctum::actingAs($pegawai, ['cuti:read']);

    $pengajuan = CutiPengajuan::factory()->create([
        'pegawai_nip' => $pegawai->nip,
        'jenis_cuti_kode' => 'CT',
    ]);

    $this->getJson('/api/cuti/pengajuan', cutiApiSignedHeaders('GET', '/api/cuti/pengajuan'))
        ->assertForbidden();

    $this->getJson("/api/cuti/pengajuan/{$pengajuan->id}", cutiApiSignedHeaders('GET', "/api/cuti/pengajuan/{$pengajuan->id}"))
        ->assertForbidden();
});

test('endpoint cuti menolak 403 token tanpa ability cuti:read dan token SyncConsumer', function () {
    $pegawai = Pegawai::factory()->create();
    cutiApiGrantPermissions($pegawai, ['cuti.pengajuan.view-all']);

    Sanctum::actingAs($pegawai, ['sync:pegawai']);
    $this->getJson('/api/cuti/pengajuan', cutiApiSignedHeaders('GET', '/api/cuti/pengajuan'))
        ->assertForbidden();

    Sanctum::actingAs($pegawai, ['app:kepegawaian']);
    $this->getJson('/api/cuti/pengajuan', cutiApiSignedHeaders('GET', '/api/cuti/pengajuan'))
        ->assertForbidden();

    $consumer = SyncConsumer::create([
        'nama' => 'Consumer Test',
        'slug' => 'consumer-test',
        'base_url' => 'https://consumer.example.test',
        'is_active' => true,
    ]);
    $consumerSecret = app(SyncConsumerCredentialService::class)->ensureHmacSecret($consumer);
    Sanctum::actingAs($consumer, ['cuti:read']);
    $this->getJson('/api/cuti/pengajuan', cutiApiSignedHeaders('GET', '/api/cuti/pengajuan', [], $consumerSecret, (string) Str::uuid()))
        ->assertForbidden();
});

test('unauthenticated returns 401', function () {
    $response = $this->getJson('/api/cuti/pengajuan');

    $response->assertUnauthorized();
});
