<?php

namespace Tests\Feature\Http\Controllers\UsulanKenaikanPangkat;

use App\Http\Controllers\UsulanKenaikanPangkat\ApprovalController;
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
use Mockery;
use Mockery\MockInterface;
use Spatie\ModelStates\Exceptions\TransitionNotFound;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;
use function Pest\Laravel\withoutVite;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    withoutVite();

    app()->instance('kp.approval.service.mock', Mockery::mock(UsulanKenaikanPangkatService::class));
    app()->instance(UsulanKenaikanPangkatService::class, app('kp.approval.service.mock'));

    Route::middleware('web')->prefix('_test/usulan-kenaikan-pangkat/approval')->name('usulan-kenaikan-pangkat.approval.')->group(function (): void {
        Route::get('/inbox', [ApprovalController::class, 'inbox'])->middleware('auth')->name('inbox');
        Route::post('/{usulan}/verifikasi-kasubbag', [ApprovalController::class, 'verifikasiKasubbag'])->middleware('auth')->name('verifikasi-kasubbag');
    });
});

afterEach(function (): void {
    Mockery::close();
});

it('menampilkan inbox approval untuk pengguna terotorisasi', function (): void {
    /** @var Pegawai $pegawai */
    $pegawai = Pegawai::factory()->create();
    grantApprovalPermission($pegawai, 'kenaikan-pangkat.usulan.verifikasi-kasubbag');
    UsulanKenaikanPangkat::factory()->diajukan()->create();

    actingAs($pegawai);

    $response = get('/_test/usulan-kenaikan-pangkat/approval/inbox');

    $response->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kenaikan-pangkat/approval/inbox')
            ->has('usulan')
            ->where('current_role', 'kasubbag')
        );
});

it('memverifikasi kasubbag valid lalu redirect back', function (): void {
    /** @var Pegawai $actor */
    $actor = Pegawai::factory()->create();
    grantApprovalPermission($actor, 'kenaikan-pangkat.usulan.verifikasi-kasubbag');
    $usulan = UsulanKenaikanPangkat::factory()->diajukan()->create();

    /** @var MockInterface $service */
    $service = app('kp.approval.service.mock');
    $service
        ->shouldReceive('verifikasiKasubbag')
        ->once()
        ->with(Mockery::on(fn (UsulanKenaikanPangkat $model): bool => $model->is($usulan)), Mockery::on(fn (Pegawai $pegawai): bool => $pegawai->is($actor)), true, 'Lengkap.')
        ->andReturnNull();

    actingAs($actor);

    $response = from('/_test/usulan-kenaikan-pangkat/approval/inbox')
        ->post("/_test/usulan-kenaikan-pangkat/approval/{$usulan->id}/verifikasi-kasubbag", [
            'setuju' => true,
            'catatan' => 'Lengkap.',
        ]);

    $response->assertRedirect('/_test/usulan-kenaikan-pangkat/approval/inbox');
});

it('mengembalikan 403 saat policy menolak aksi verifikasi kasubbag', function (): void {
    /** @var Pegawai $actor */
    $actor = Pegawai::factory()->create();
    $usulan = UsulanKenaikanPangkat::factory()->diajukan()->create();

    actingAs($actor);

    $response = from('/_test/usulan-kenaikan-pangkat/approval/inbox')
        ->post("/_test/usulan-kenaikan-pangkat/approval/{$usulan->id}/verifikasi-kasubbag", [
            'setuju' => true,
            'catatan' => 'Lengkap.',
        ]);

    $response->assertForbidden();
});

it('mengembalikan 403 saat viewer atau pemilik memanggil mintaPerbaikan dan tolak', function (): void {
    /** @var Pegawai $owner */
    $owner = Pegawai::factory()->create();
    /** @var Pegawai $viewer */
    $viewer = Pegawai::factory()->create();
    grantApprovalPermission($viewer, 'kenaikan-pangkat.usulan.view');

    $usulan = UsulanKenaikanPangkat::factory()->create([
        'pegawai_id' => $owner->id,
        'state' => 'DIVERIFIKASI_KASUBBAG',
    ]);

    actingAs($owner)
        ->post(route('kenaikan-pangkat.approval.minta-perbaikan', $usulan), ['catatan' => 'Self bypass'])
        ->assertForbidden();

    actingAs($viewer)
        ->post(route('kenaikan-pangkat.approval.tolak', $usulan), ['alasan' => 'Viewer bypass'])
        ->assertForbidden();
});

it('menampilkan semua state untuk approver multi-role di inbox', function (): void {
    /** @var Pegawai $approver */
    $approver = Pegawai::factory()->create();
    grantApprovalPermission($approver, 'kenaikan-pangkat.usulan.verifikasi-kasubbag');
    grantApprovalPermission($approver, 'kenaikan-pangkat.usulan.verifikasi-sekretaris');
    $approver->refresh();

    UsulanKenaikanPangkat::factory()->diajukan()->create();
    UsulanKenaikanPangkat::factory()->create(['state' => 'DIVERIFIKASI_KASUBBAG']);

    actingAs($approver)
        ->get(route('kenaikan-pangkat.approval.inbox'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kenaikan-pangkat/approval/inbox')
            ->has('usulan.data', 2)
        );
});

function grantApprovalPermission(Pegawai $pegawai, string $slug): void
{
    $app = IamApplication::query()->firstOrCreate(
        ['slug' => (string) config('iam.app_slug', 'kepegawaian')],
        ['nama' => 'Kepegawaian', 'url' => (string) config('app.url')]
    );

    $role = IamRole::query()->firstOrCreate(
        ['slug' => 'kp-approval-controller-role', 'iam_application_id' => $app->id],
        ['nama' => 'KP Approval Controller']
    );

    $permission = IamPermission::query()->firstOrCreate(
        ['slug' => $slug, 'iam_application_id' => $app->id],
        ['nama' => $slug]
    );

    $role->permissions()->syncWithoutDetaching([$permission->id]);
    $pegawai->iamRoles()->syncWithoutDetaching([$role->id]);
}

it('menolak self-approval HTTP 403 pada seluruh 5 tahap approval dan upload SK KP', function (): void {
    /** @var Pegawai $owner */
    $owner = Pegawai::factory()->create();

    foreach ([
        'kenaikan-pangkat.usulan.verifikasi-kasubbag',
        'kenaikan-pangkat.usulan.verifikasi-sekretaris',
        'kenaikan-pangkat.usulan.tanda-tangan-ketua',
        'kenaikan-pangkat.usulan.kirim-biro',
        'kenaikan-pangkat.usulan.upload-sk',
    ] as $permission) {
        grantApprovalPermission($owner, $permission);
    }
    $owner->refresh();

    $usulanDiajukan = UsulanKenaikanPangkat::factory()->create(['pegawai_id' => $owner->id, 'state' => 'DIAJUKAN']);
    $usulanKasubbag = UsulanKenaikanPangkat::factory()->create(['pegawai_id' => $owner->id, 'state' => 'DIVERIFIKASI_KASUBBAG']);
    $usulanSekretaris = UsulanKenaikanPangkat::factory()->create(['pegawai_id' => $owner->id, 'state' => 'DIVERIFIKASI_SEKRETARIS']);
    $usulanKetua = UsulanKenaikanPangkat::factory()->create(['pegawai_id' => $owner->id, 'state' => 'DITANDATANGANI_KETUA']);
    $usulanMenungguSk = UsulanKenaikanPangkat::factory()->create(['pegawai_id' => $owner->id, 'state' => 'MENUNGGU_SK']);

    actingAs($owner);

    post(route('kenaikan-pangkat.approval.verifikasi-kasubbag', $usulanDiajukan), ['setuju' => true])
        ->assertForbidden();
    post(route('kenaikan-pangkat.approval.verifikasi-sekretaris', $usulanKasubbag), ['setuju' => true])
        ->assertForbidden();
    post(route('kenaikan-pangkat.approval.tanda-tangan-ketua', $usulanSekretaris))
        ->assertForbidden();
    post(route('kenaikan-pangkat.approval.kirim-biro', $usulanKetua))
        ->assertForbidden();
    post(route('kenaikan-pangkat.admin-sk.upload-sk', $usulanMenungguSk), [
        'sk_file' => UploadedFile::fake()->create('sk.pdf', 64, 'application/pdf'),
        'nomor_sk' => 'SK-SELF-001',
        'tanggal_sk' => now()->toDateString(),
        'tmt_pangkat_baru' => now()->toDateString(),
        'masa_kerja_tahun' => 8,
        'masa_kerja_bulan' => 4,
    ])->assertForbidden();
});

it('menangani TransitionNotFound pada tandaTanganKetua sebagai validation error tanpa 500', function (): void {
    /** @var Pegawai $ketua */
    $ketua = Pegawai::factory()->create();
    grantApprovalPermission($ketua, 'kenaikan-pangkat.usulan.tanda-tangan-ketua');
    $usulan = UsulanKenaikanPangkat::factory()->create(['state' => 'DIVERIFIKASI_SEKRETARIS']);

    /** @var MockInterface $service */
    $service = app('kp.approval.service.mock');
    $service->shouldReceive('tandaTanganKetua')
        ->once()
        ->andThrow(TransitionNotFound::make('DITANDATANGANI_KETUA', 'DITANDATANGANI_KETUA', UsulanKenaikanPangkat::class));

    actingAs($ketua);

    from(route('kenaikan-pangkat.approval.inbox'))
        ->post(route('kenaikan-pangkat.approval.tanda-tangan-ketua', $usulan))
        ->assertRedirect(route('kenaikan-pangkat.approval.inbox'))
        ->assertSessionHasErrors('usulan');
});

it('memaginasi inbox approval lebih dari 10 usulan dan memvalidasi batas 1000 karakter', function (): void {
    /** @var Pegawai $kasubbag */
    $kasubbag = Pegawai::factory()->create();
    grantApprovalPermission($kasubbag, 'kenaikan-pangkat.usulan.verifikasi-kasubbag');
    UsulanKenaikanPangkat::factory()->count(12)->diajukan()->create();

    actingAs($kasubbag);

    get(route('kenaikan-pangkat.approval.inbox'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kenaikan-pangkat/approval/inbox')
            ->has('usulan.data', 10)
            ->where('usulan.total', 12)
            ->where('usulan.last_page', 2)
            ->has('usulan.links', 4)
        );

    get(route('kenaikan-pangkat.approval.inbox', ['page' => 2]))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kenaikan-pangkat/approval/inbox')
            ->has('usulan.data', 2)
            ->where('usulan.current_page', 2)
        );

    $usulanSekretaris = UsulanKenaikanPangkat::factory()->create(['state' => 'DIVERIFIKASI_KASUBBAG']);
    /** @var Pegawai $sekretaris */
    $sekretaris = Pegawai::factory()->create();
    grantApprovalPermission($sekretaris, 'kenaikan-pangkat.usulan.verifikasi-sekretaris');
    $teksTerlaluPanjang = str_repeat('a', 1001);

    actingAs($sekretaris);

    from(route('kenaikan-pangkat.approval.inbox'))
        ->post(route('kenaikan-pangkat.approval.minta-perbaikan', $usulanSekretaris), [
            'catatan' => $teksTerlaluPanjang,
        ])
        ->assertSessionHasErrors('catatan');

    from(route('kenaikan-pangkat.approval.inbox'))
        ->post(route('kenaikan-pangkat.approval.tolak', $usulanSekretaris), [
            'alasan' => $teksTerlaluPanjang,
        ])
        ->assertSessionHasErrors('alasan');
});

it('menangani TransitionNotFound pada verifikasiKasubbag, verifikasiSekretaris, dan kirimBiro sebagai HTTP 422 bukan 500', function (string $routeName, string $permission, string $state, string $serviceMethod, array $payload): void {
    /** @var Pegawai $actor */
    $actor = Pegawai::factory()->create();
    grantApprovalPermission($actor, $permission);
    $usulan = UsulanKenaikanPangkat::factory()->create(['state' => $state]);

    /** @var MockInterface $service */
    $service = app('kp.approval.service.mock');
    $service->shouldReceive($serviceMethod)
        ->once()
        ->andThrow(TransitionNotFound::make('DIBATALKAN', $state, UsulanKenaikanPangkat::class));

    actingAs($actor);

    postJson(route($routeName, $usulan), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('usulan');
})->with([
    'verifikasiKasubbag' => [
        'kenaikan-pangkat.approval.verifikasi-kasubbag',
        'kenaikan-pangkat.usulan.verifikasi-kasubbag',
        'DIAJUKAN',
        'verifikasiKasubbag',
        ['setuju' => true, 'catatan' => 'OK'],
    ],
    'verifikasiSekretaris' => [
        'kenaikan-pangkat.approval.verifikasi-sekretaris',
        'kenaikan-pangkat.usulan.verifikasi-sekretaris',
        'DIVERIFIKASI_KASUBBAG',
        'verifikasiSekretaris',
        ['setuju' => true, 'catatan' => 'OK'],
    ],
    'kirimBiro' => [
        'kenaikan-pangkat.approval.kirim-biro',
        'kenaikan-pangkat.usulan.kirim-biro',
        'DITANDATANGANI_KETUA',
        'kirimBiro',
        ['catatan' => 'Kirim'],
    ],
]);

it('menerima setuju true dengan catatan kosong atau null dan menolak setuju false tanpa catatan pada verifikasi kasubbag dan sekretaris', function (): void {
    /** @var Pegawai $kasubbag */
    $kasubbag = Pegawai::factory()->create();
    grantApprovalPermission($kasubbag, 'kenaikan-pangkat.usulan.verifikasi-kasubbag');
    /** @var Pegawai $sekretaris */
    $sekretaris = Pegawai::factory()->create();
    grantApprovalPermission($sekretaris, 'kenaikan-pangkat.usulan.verifikasi-sekretaris');

    $usulanKasubbag = UsulanKenaikanPangkat::factory()->diajukan()->create();
    $usulanSekretaris = UsulanKenaikanPangkat::factory()->create(['state' => 'DIVERIFIKASI_KASUBBAG']);

    /** @var MockInterface $service */
    $service = app('kp.approval.service.mock');
    $service->shouldReceive('verifikasiKasubbag')
        ->twice()
        ->with(Mockery::on(fn (UsulanKenaikanPangkat $model): bool => $model->is($usulanKasubbag)), Mockery::on(fn (Pegawai $pegawai): bool => $pegawai->is($kasubbag)), true, null)
        ->andReturnNull();
    $service->shouldReceive('verifikasiSekretaris')
        ->twice()
        ->with(Mockery::on(fn (UsulanKenaikanPangkat $model): bool => $model->is($usulanSekretaris)), Mockery::on(fn (Pegawai $pegawai): bool => $pegawai->is($sekretaris)), true, null)
        ->andReturnNull();

    actingAs($kasubbag);
    from(route('kenaikan-pangkat.approval.inbox'))
        ->post(route('kenaikan-pangkat.approval.verifikasi-kasubbag', $usulanKasubbag), [
            'setuju' => true,
            'catatan' => '',
        ])
        ->assertRedirect(route('kenaikan-pangkat.approval.inbox'));

    from(route('kenaikan-pangkat.approval.inbox'))
        ->post(route('kenaikan-pangkat.approval.verifikasi-kasubbag', $usulanKasubbag), [
            'setuju' => true,
            'catatan' => null,
        ])
        ->assertRedirect(route('kenaikan-pangkat.approval.inbox'));

    postJson(route('kenaikan-pangkat.approval.verifikasi-kasubbag', $usulanKasubbag), [
        'setuju' => false,
        'catatan' => '',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('catatan');

    actingAs($sekretaris);
    from(route('kenaikan-pangkat.approval.inbox'))
        ->post(route('kenaikan-pangkat.approval.verifikasi-sekretaris', $usulanSekretaris), [
            'setuju' => true,
            'catatan' => '',
        ])
        ->assertRedirect(route('kenaikan-pangkat.approval.inbox'));

    from(route('kenaikan-pangkat.approval.inbox'))
        ->post(route('kenaikan-pangkat.approval.verifikasi-sekretaris', $usulanSekretaris), [
            'setuju' => true,
            'catatan' => null,
        ])
        ->assertRedirect(route('kenaikan-pangkat.approval.inbox'));

    postJson(route('kenaikan-pangkat.approval.verifikasi-sekretaris', $usulanSekretaris), [
        'setuju' => false,
        'catatan' => '',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('catatan');
});
