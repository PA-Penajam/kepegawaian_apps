<?php

use App\Http\Controllers\UsulanKenaikanPangkat\SkAdminController;
use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\Pegawai;
use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;
use App\Models\UsulanKenaikanPangkat\UsulanKpPdf;
use App\Services\UsulanKenaikanPangkat\UsulanKenaikanPangkatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\ModelStates\Exceptions\TransitionNotFound;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    Route::get('/_test/kp/admin-sk', [SkAdminController::class, 'index'])
        ->middleware(['web', 'auth'])
        ->name('test.kp.admin-sk.index');

    Route::post('/_test/kp/admin-sk/{usulan}/upload', [SkAdminController::class, 'uploadSk'])
        ->middleware(['web', 'auth'])
        ->name('test.kp.admin-sk.upload');
});

function grantSkAdminPermission(Pegawai $pegawai, string $slug): void
{
    $app = IamApplication::query()->firstOrCreate(
        ['slug' => (string) config('iam.app_slug', 'kepegawaian')],
        ['nama' => 'Kepegawaian', 'url' => (string) config('app.url')]
    );

    $role = IamRole::query()->firstOrCreate(
        ['slug' => 'kp-admin-sk', 'iam_application_id' => $app->id],
        ['nama' => 'KP Admin SK']
    );

    $permission = IamPermission::query()->firstOrCreate(
        ['slug' => $slug, 'iam_application_id' => $app->id],
        ['nama' => $slug]
    );

    $role->permissions()->syncWithoutDetaching([$permission->id]);
    $pegawai->iamRoles()->syncWithoutDetaching([$role->id]);
}

function makeSkAdminPegawai(): Pegawai
{
    $pegawai = Pegawai::factory()->create();

    assert($pegawai instanceof Pegawai);

    return $pegawai;
}

it('menampilkan daftar usulan admin SK untuk user berizin', function (): void {
    $user = makeSkAdminPegawai();
    grantSkAdminPermission($user, 'kenaikan-pangkat.usulan.view');
    UsulanKenaikanPangkat::factory()->create(['state' => 'MENUNGGU_SK']);
    UsulanKenaikanPangkat::factory()->skTerbit()->create();
    UsulanKenaikanPangkat::factory()->create(['state' => 'DRAFT']);

    actingAs($user)
        ->withoutVite()
        ->get('/_test/kp/admin-sk')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kenaikan-pangkat/admin-sk/index')
            ->has('usulan.data', 2)
        );
});

it('mengunggah SK final PDF valid lalu redirect kembali', function (): void {
    $user = makeSkAdminPegawai();
    grantSkAdminPermission($user, 'kenaikan-pangkat.usulan.upload-sk');
    $usulan = UsulanKenaikanPangkat::factory()->create(['state' => 'MENUNGGU_SK']);
    $file = UploadedFile::fake()->create('sk.pdf', 128, 'application/pdf');

    $service = Mockery::mock(UsulanKenaikanPangkatService::class);
    $service->shouldReceive('uploadSkFinal')
        ->once()
        ->withArgs(fn (
            UsulanKenaikanPangkat $actualUsulan,
            Pegawai $actor,
            UploadedFile $actualFile,
            string $nomorSk,
            string $tanggalSk,
            ?string $tmtPangkatBaru,
            ?int $masaKerjaTahun,
            ?int $masaKerjaBulan,
        ): bool => $actualUsulan->is($usulan)
            && $actor->is($user)
            && $actualFile === $file
            && $nomorSk === 'SK-001'
            && $tanggalSk === now()->toDateString()
            && $tmtPangkatBaru === now()->toDateString()
            && $masaKerjaTahun === 8
            && $masaKerjaBulan === 4);
    app()->instance(UsulanKenaikanPangkatService::class, $service);

    actingAs($user);

    post("/_test/kp/admin-sk/{$usulan->id}/upload", [
        'sk_file' => $file,
        'nomor_sk' => 'SK-001',
        'tanggal_sk' => now()->toDateString(),
        'tmt_pangkat_baru' => now()->toDateString(),
        'masa_kerja_tahun' => 8,
        'masa_kerja_bulan' => 4,
    ])->assertRedirect()
        ->assertSessionHas('success', 'SK kenaikan pangkat berhasil diunggah.');
});

it('menolak upload SK selain PDF', function (): void {
    $user = makeSkAdminPegawai();
    grantSkAdminPermission($user, 'kenaikan-pangkat.usulan.upload-sk');
    $usulan = UsulanKenaikanPangkat::factory()->create(['state' => 'MENUNGGU_SK']);

    actingAs($user);

    postJson("/_test/kp/admin-sk/{$usulan->id}/upload", [
        'sk_file' => UploadedFile::fake()->create('sk.txt', 16, 'text/plain'),
        'nomor_sk' => 'SK-001',
        'tanggal_sk' => now()->toDateString(),
        'tmt_pangkat_baru' => now()->toDateString(),
        'masa_kerja_tahun' => 8,
        'masa_kerja_bulan' => 4,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('sk_file');
});

it('menolak upload SK ketika policy tidak mengizinkan', function (): void {
    $user = makeSkAdminPegawai();
    $usulan = UsulanKenaikanPangkat::factory()->create(['state' => 'MENUNGGU_SK']);

    actingAs($user);

    post("/_test/kp/admin-sk/{$usulan->id}/upload", [
        'sk_file' => UploadedFile::fake()->create('sk.pdf', 128, 'application/pdf'),
        'nomor_sk' => 'SK-001',
        'tanggal_sk' => now()->toDateString(),
        'tmt_pangkat_baru' => now()->toDateString(),
        'masa_kerja_tahun' => 8,
        'masa_kerja_bulan' => 4,
    ])->assertForbidden();
});

it('mengembalikan 404 saat file SK atau surat pengantar tidak ditemukan di disk', function (): void {
    Storage::fake('local');

    $user = makeSkAdminPegawai();
    grantSkAdminPermission($user, 'kenaikan-pangkat.usulan.view');

    $usulan = UsulanKenaikanPangkat::factory()->create([
        'pegawai_id' => $user->id,
        'state' => 'MENUNGGU_SK',
        'sk_file_path' => null,
    ]);

    $pdf = UsulanKpPdf::query()->create([
        'usulan_kenaikan_pangkat_id' => $usulan->id,
        'jenis_pdf' => 'surat_pengantar',
        'nomor_surat' => 'W1-U1/1/KP.01.1/V/2026',
        'file_path' => 'usulan-kp/surat-pengantar/hilang.pdf',
        'generated_by' => $user->id,
        'generated_at' => now(),
    ]);

    actingAs($user)
        ->get(route('kenaikan-pangkat.admin-sk.download-sk', $usulan))
        ->assertNotFound();

    actingAs($user)
        ->get(route('kenaikan-pangkat.admin-sk.download-surat-pengantar', $pdf))
        ->assertNotFound();
});

it('menangani TransitionNotFound pada uploadSk sebagai validation error 422 tanpa 500', function (): void {
    $user = makeSkAdminPegawai();
    grantSkAdminPermission($user, 'kenaikan-pangkat.usulan.upload-sk');
    $usulan = UsulanKenaikanPangkat::factory()->create(['state' => 'MENUNGGU_SK']);

    $service = Mockery::mock(UsulanKenaikanPangkatService::class);
    $service->shouldReceive('uploadSkFinal')
        ->once()
        ->andThrow(TransitionNotFound::make('SELESAI_SK_TERBIT', 'SELESAI_SK_TERBIT', UsulanKenaikanPangkat::class));
    app()->instance(UsulanKenaikanPangkatService::class, $service);

    actingAs($user);

    postJson("/_test/kp/admin-sk/{$usulan->id}/upload", [
        'sk_file' => UploadedFile::fake()->create('sk.pdf', 128, 'application/pdf'),
        'nomor_sk' => 'SK-001',
        'tanggal_sk' => now()->toDateString(),
        'tmt_pangkat_baru' => now()->toDateString(),
        'masa_kerja_tahun' => 8,
        'masa_kerja_bulan' => 4,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('usulan');
});

it('menjepit parameter per_page di antara 1 sampai 100 pada index admin SK', function (): void {
    $user = makeSkAdminPegawai();
    grantSkAdminPermission($user, 'kenaikan-pangkat.usulan.view');
    UsulanKenaikanPangkat::factory()->create(['state' => 'MENUNGGU_SK']);

    actingAs($user)
        ->withoutVite()
        ->get('/_test/kp/admin-sk?per_page=-5')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kenaikan-pangkat/admin-sk/index')
            ->where('filters.per_page', 1)
            ->where('usulan.per_page', 1)
        );

    actingAs($user)
        ->withoutVite()
        ->get('/_test/kp/admin-sk?per_page=9999')
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kenaikan-pangkat/admin-sk/index')
            ->where('filters.per_page', 100)
            ->where('usulan.per_page', 100)
        );
});

it('mensanitasi sk_file_original_name yang mengandung slash atau backslash saat download SK', function (): void {
    Storage::fake('local');

    $user = makeSkAdminPegawai();
    grantSkAdminPermission($user, 'kenaikan-pangkat.usulan.view');

    $path = 'usulan-kp/sk/sample-sk.pdf';
    Storage::disk('local')->put($path, '%PDF-1.4 sample');

    $usulanSlash = UsulanKenaikanPangkat::factory()->create([
        'pegawai_id' => $user->id,
        'state' => 'SELESAI_SK_TERBIT',
        'sk_file_path' => $path,
        'sk_file_original_name' => '../../evil\\sk-final.pdf',
    ]);

    actingAs($user)
        ->get(route('kenaikan-pangkat.admin-sk.download-sk', $usulanSlash))
        ->assertOk()
        ->assertDownload('sk-final.pdf');

    $usulanEmpty = UsulanKenaikanPangkat::factory()->create([
        'pegawai_id' => $user->id,
        'state' => 'SELESAI_SK_TERBIT',
        'sk_file_path' => $path,
        'sk_file_original_name' => '/\\..',
    ]);

    actingAs($user)
        ->get(route('kenaikan-pangkat.admin-sk.download-sk', $usulanEmpty))
        ->assertOk()
        ->assertDownload("sk-kenaikan-pangkat-{$usulanEmpty->id}.pdf");
});
