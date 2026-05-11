<?php

use App\Http\Controllers\UsulanKenaikanPangkat\SkAdminController;
use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\Pegawai;
use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;
use App\Services\UsulanKenaikanPangkat\UsulanKenaikanPangkatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;

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
        ['slug' => 'test-kp'],
        ['nama' => 'KP Test', 'url' => 'https://local']
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
        ->withArgs(fn (UsulanKenaikanPangkat $actualUsulan, Pegawai $actor, UploadedFile $actualFile, string $nomorSk, string $tanggalSk): bool => $actualUsulan->is($usulan)
            && $actor->is($user)
            && $actualFile === $file
            && $nomorSk === 'SK-001'
            && $tanggalSk === now()->toDateString());
    app()->instance(UsulanKenaikanPangkatService::class, $service);

    actingAs($user);

    post("/_test/kp/admin-sk/{$usulan->id}/upload", [
        'sk_file' => $file,
        'nomor_sk' => 'SK-001',
        'tanggal_sk' => now()->toDateString(),
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
    ])->assertForbidden();
});
