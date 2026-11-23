<?php

use App\Http\Middleware\VerifyHmacSignature;
use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\Pegawai;
use Database\Seeders\CutiPermissionSeeder;
use Database\Seeders\PermissionSikepP1Seeder;
use Laravel\Sanctum\Sanctum;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\getJson;
use function Pest\Laravel\withoutMiddleware;
use function Pest\Laravel\withoutVite;

function makeSuperAdminPegawaiForQueryTest(): Pegawai
{
    /** @var Pegawai $admin */
    $admin = Pegawai::factory()->admin()->create();

    $app = IamApplication::query()->firstOrCreate(
        ['slug' => (string) config('iam.app_slug', 'kepegawaian')],
        ['nama' => 'Kepegawaian', 'url' => (string) config('app.url')]
    );

    $role = IamRole::query()->firstOrCreate(
        ['slug' => 'query-hardening-admin', 'iam_application_id' => $app->id],
        ['nama' => 'Query Hardening Admin']
    );

    $permissions = [
        'pegawai.view',
        'cuti.audit.view',
        'cuti.pengajuan.view-all',
        'cuti.saldo.adjust',
        'referensi.view',
        'rbac.manage',
        'iam.manage',
        'kenaikan-pangkat.usulan.view',
        'kenaikan-pangkat.monitoring.view',
        'monitoring.view',
        'checklist.template.view',
    ];

    $permissionIds = collect($permissions)->map(fn (string $slug): string => IamPermission::query()->firstOrCreate(
        ['slug' => $slug, 'iam_application_id' => $app->id],
        ['nama' => $slug]
    )->id)->all();

    $role->permissions()->syncWithoutDetaching($permissionIds);
    $admin->iamRoles()->syncWithoutDetaching([$role->id]);

    return $admin->refresh();
}

it('menangani query parameter array tanpa HTTP 500 pada API UsulanKenaikanPangkat dan Pegawai', function (): void {
    withoutMiddleware([VerifyHmacSignature::class]);

    $user = Pegawai::factory()->create();
    Sanctum::actingAs($user, ['*']);

    getJson('/api/kenaikan-pangkat/usulan?state[]=DRAFT&pegawai_id[]=1&periode_usul_bulan[]=4&periode_usul_tahun[]=2026')
        ->assertSuccessful();

    getJson('/api/kenaikan-pangkat/stats?state[]=DRAFT&periode_usul_bulan[]=4&periode_usul_tahun[]=2026')
        ->assertSuccessful();

    getJson('/api/v1/pegawai?search[]=foo&status[]=aktif')
        ->assertSuccessful();

    getJson('/api/cuti/pengajuan?state[]=DIAJUKAN&pegawai_nip[]=123&tahun[]=2026')
        ->assertForbidden(); // Belum memiliki izin cuti.pengajuan.view-* — bukan 500
});

it('menangani query parameter array tanpa HTTP 500 pada controller web dan API cuti terotorisasi', function (): void {
    $this->seed(CutiPermissionSeeder::class);
    $this->seed(PermissionSikepP1Seeder::class);
    withoutVite();

    $admin = makeSuperAdminPegawaiForQueryTest();
    actingAs($admin);

    $role = IamRole::query()->firstOrFail();

    $endpoints = [
        '/kepegawaian/pegawai?search[]=foo&sort_dir[]=asc&sort_by[]=nama&unit_kerja[]=1&jabatan[]=1&golongan[]=III&status_pegawai[]=Aktif',
        '/admin/cuti/audit?aktor[]=foo&from[]=2026-01-01&to[]=2026-12-31',
        '/admin/cuti/saldo?tahun[]=2026',
        '/referensi/roles?search[]=foo',
        "/referensi/roles/{$role->id}/edit?search_pegawai[]=foo",
        '/referensi/status-kepegawaian?search[]=foo',
        '/referensi/jenis-dokumen?search[]=foo',
        '/referensi/status-pegawai?search[]=foo',
        '/activity-log?subject_type[]=Pegawai&causer_id[]=1&date_from[]=2026-01-01&date_to[]=2026-12-31',
        '/iam/users?search[]=foo',
        '/kenaikan-pangkat/usulan?state[][]=DRAFT&periode[]=2026-04&search[]=foo',
        '/kenaikan-pangkat/admin-sk?state[]=MENUNGGU_SK',
        '/kenaikan-pangkat/eligible?unit_kerja[]=1&golongan[]=III',
        '/admin/checklist-template?jenis[]=kenaikan_pangkat',
        '/kepegawaian/monitoring/kenaikan-pangkat?unit_kerja[]=1&golongan[]=III',
        '/kepegawaian/monitoring/kgb?unit_kerja[]=1&golongan[]=III&status[]=due',
    ];

    foreach ($endpoints as $uri) {
        $response = get($uri);
        expect($response->status())->toBeLessThan(500, "Endpoint {$uri} mengembalikan status {$response->status()}");
        $response->assertSuccessful();
    }

    withoutMiddleware([VerifyHmacSignature::class]);
    Sanctum::actingAs($admin, ['*']);

    getJson('/api/cuti/pengajuan?state[]=DIAJUKAN&pegawai_nip[]=123&tahun[]=2026')
        ->assertSuccessful();

    getJson("/api/cuti/saldo/{$admin->nip}/ledger?tahun_hak[]=2026")
        ->assertSuccessful();
});
