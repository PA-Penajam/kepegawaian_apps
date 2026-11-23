<?php

namespace Tests\Feature\Http\Controllers\UsulanKenaikanPangkat;

use App\Exceptions\UsulanKenaikanPangkat\DuplicateUsulanException;
use App\Exceptions\UsulanKenaikanPangkat\PegawaiTidakEligibleException;
use App\Models\BerkasChecklist\BerkasChecklistSubmission;
use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\Pegawai;
use App\Models\RefPangkat;
use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;
use App\Services\UsulanKenaikanPangkat\UsulanKenaikanPangkatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Spatie\ModelStates\Exceptions\TransitionNotFound;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\postJson;
use function Pest\Laravel\put;
use function Pest\Laravel\withoutVite;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->instance(UsulanKenaikanPangkatService::class, Mockery::mock(UsulanKenaikanPangkatService::class));
    withoutVite();
});

afterEach(function (): void {
    Mockery::close();
});

it('menampilkan daftar usulan untuk pengguna terotorisasi', function (): void {
    $pegawai = Pegawai::factory()->create();
    grantPermission($pegawai, 'kenaikan-pangkat.usulan.view');
    UsulanKenaikanPangkat::factory()->create(['pegawai_id' => $pegawai->id]);

    actingAs($pegawai);

    $response = get('/kenaikan-pangkat/usulan?state=DRAFT');

    $response->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kenaikan-pangkat/usulan/index')
            ->has('usulanList')
            ->where('filters.state', 'DRAFT')
        );
});

it('menyimpan draft valid dan redirect ke detail usulan', function (): void {
    $actor = Pegawai::factory()->create();
    $pegawai = Pegawai::factory()->create();
    $pangkat = RefPangkat::factory()->create();
    grantPermission($actor, 'kenaikan-pangkat.usulan.create');
    $usulan = UsulanKenaikanPangkat::factory()->create(['pegawai_id' => $pegawai->id]);

    app(UsulanKenaikanPangkatService::class)
        ->shouldReceive('createDraft')
        ->once()
        ->with(Mockery::on(fn (array $data): bool => $data['pegawai_id'] === $pegawai->id), Mockery::on(fn (Pegawai $user): bool => $user->is($actor)))
        ->andReturn($usulan);

    actingAs($actor);

    $response = post('/kenaikan-pangkat/usulan', [
        'pegawai_id' => $pegawai->id,
        'ref_pangkat_tujuan_id' => $pangkat->id,
        'periode_usul_bulan' => 4,
        'periode_usul_tahun' => now()->year,
        'catatan_pengusul' => 'Layak diusulkan.',
    ]);

    $response->assertRedirect(route('kenaikan-pangkat.usulan.show', $usulan));
});

it('mengubah exception domain saat store menjadi validation error', function (string $exceptionClass): void {
    $actor = Pegawai::factory()->create();
    $pegawai = Pegawai::factory()->create();
    $pangkat = RefPangkat::factory()->create();
    grantPermission($actor, 'kenaikan-pangkat.usulan.create');

    app(UsulanKenaikanPangkatService::class)
        ->shouldReceive('createDraft')
        ->once()
        ->andThrow(new $exceptionClass('Gagal validasi domain.'));

    actingAs($actor);

    $response = from('/kenaikan-pangkat/usulan/create')->post('/kenaikan-pangkat/usulan', [
        'pegawai_id' => $pegawai->id,
        'ref_pangkat_tujuan_id' => $pangkat->id,
        'periode_usul_bulan' => 4,
        'periode_usul_tahun' => now()->year,
    ]);

    $response->assertSessionHasErrors('pegawai_id');
})->with([
    'pegawai tidak eligible' => PegawaiTidakEligibleException::class,
    'usulan duplikat' => DuplicateUsulanException::class,
]);

it('memperbarui draft usulan melalui UpdateUsulanKenaikanPangkatRequest', function (): void {
    $actor = Pegawai::factory()->create();
    $pangkatTujuan = RefPangkat::factory()->create();
    $usulan = UsulanKenaikanPangkat::factory()->create(['pegawai_id' => $actor->id]);

    app(UsulanKenaikanPangkatService::class)
        ->shouldReceive('update')
        ->once()
        ->with(
            Mockery::on(fn (UsulanKenaikanPangkat $model): bool => $model->is($usulan)),
            Mockery::on(fn (array $data): bool => $data['ref_pangkat_tujuan_id'] === $pangkatTujuan->id),
        )
        ->andReturn($usulan);

    actingAs($actor);

    $response = put(route('kenaikan-pangkat.usulan.update', $usulan), [
        'pegawai_id' => $actor->id,
        'ref_pangkat_tujuan_id' => $pangkatTujuan->id,
        'periode_usul_bulan' => 10,
        'periode_usul_tahun' => now()->year,
        'catatan_pengusul' => 'Diperbarui.',
    ]);

    $response->assertRedirect(route('kenaikan-pangkat.usulan.show', $usulan));
});

it('menolak payload store tidak valid', function (): void {
    $actor = Pegawai::factory()->create();
    grantPermission($actor, 'kenaikan-pangkat.usulan.create');

    actingAs($actor);

    $response = from('/kenaikan-pangkat/usulan/create')->post('/kenaikan-pangkat/usulan', []);

    $response->assertSessionHasErrors(['pegawai_id', 'ref_pangkat_tujuan_id', 'periode_usul_bulan', 'periode_usul_tahun']);
});

it('mengembalikan 403 saat policy menolak akses', function (): void {
    $pegawai = Pegawai::factory()->create();

    actingAs($pegawai);

    $response = get('/kenaikan-pangkat/usulan');

    $response->assertForbidden();
});

it('mengalihkan guest ke login', function (): void {
    $response = get('/kenaikan-pangkat/usulan');

    $response->assertRedirect();
});

function grantPermission(Pegawai $pegawai, string $slug): void
{
    $app = IamApplication::query()->firstOrCreate(
        ['slug' => (string) config('iam.app_slug', 'kepegawaian')],
        ['nama' => 'Kepegawaian', 'url' => (string) config('app.url')]
    );

    $role = IamRole::query()->firstOrCreate(
        ['slug' => 'kp-controller-role', 'iam_application_id' => $app->id],
        ['nama' => 'KP Controller']
    );

    $permission = IamPermission::query()->firstOrCreate(
        ['slug' => $slug, 'iam_application_id' => $app->id],
        ['nama' => $slug]
    );

    $role->permissions()->syncWithoutDetaching([$permission->id]);
    $pegawai->iamRoles()->syncWithoutDetaching([$role->id]);
}

it('menangani TransitionNotFound pada submit sebagai validation error 422 bukan 500', function (): void {
    $actor = Pegawai::factory()->create();
    grantPermission($actor, 'kenaikan-pangkat.usulan.submit');
    $usulan = UsulanKenaikanPangkat::factory()->create([
        'pegawai_id' => $actor->id,
        'created_by' => $actor->id,
        'state' => 'DRAFT',
    ]);

    app(UsulanKenaikanPangkatService::class)
        ->shouldReceive('submit')
        ->once()
        ->andThrow(TransitionNotFound::make('DIBATALKAN', 'DIAJUKAN', UsulanKenaikanPangkat::class));

    actingAs($actor);

    postJson(route('kenaikan-pangkat.usulan.submit', $usulan))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('usulan');
});

it('menolak checklist_submission_id bertipe array dengan 422 pada submit usulan', function (): void {
    $actor = Pegawai::factory()->create();
    grantPermission($actor, 'kenaikan-pangkat.usulan.submit');
    $usulan = UsulanKenaikanPangkat::factory()->create([
        'pegawai_id' => $actor->id,
        'created_by' => $actor->id,
        'state' => 'DRAFT',
    ]);
    $submission = BerkasChecklistSubmission::factory()->create([
        'pegawai_id' => $actor->id,
        'subject_type' => $usulan->getMorphClass(),
        'subject_id' => $usulan->id,
    ]);

    app(UsulanKenaikanPangkatService::class)
        ->shouldNotReceive('submit');

    actingAs($actor);

    postJson(route('kenaikan-pangkat.usulan.submit', $usulan), [
        'checklist_submission_id' => [$submission->id],
    ])->assertUnprocessable()
        ->assertJsonValidationErrors('checklist_submission_id');
});

it('menangani TransitionNotFound pada update sebagai validation error 422 bukan 500', function (): void {
    $actor = Pegawai::factory()->create();
    $pangkatTujuan = RefPangkat::factory()->create();
    $usulan = UsulanKenaikanPangkat::factory()->create([
        'pegawai_id' => $actor->id,
        'created_by' => $actor->id,
        'state' => 'DRAFT',
    ]);

    app(UsulanKenaikanPangkatService::class)
        ->shouldReceive('update')
        ->once()
        ->andThrow(TransitionNotFound::make('DIAJUKAN', 'DRAFT', UsulanKenaikanPangkat::class));

    actingAs($actor);

    $this->putJson(route('kenaikan-pangkat.usulan.update', $usulan), [
        'pegawai_id' => $actor->id,
        'ref_pangkat_tujuan_id' => $pangkatTujuan->id,
        'periode_usul_bulan' => 10,
        'periode_usul_tahun' => now()->year,
        'catatan_pengusul' => 'Diperbarui saat race condition.',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('usulan');
});

it('menolak pegawai_id dan ref_pangkat_tujuan_id yang di-soft-delete dengan HTTP 422 bukan 404 saat store usulan', function (): void {
    $actor = Pegawai::factory()->create();
    grantPermission($actor, 'kenaikan-pangkat.usulan.create');

    $pegawaiTerhapus = Pegawai::factory()->create();
    $pegawaiTerhapus->delete();

    $pegawaiAktif = Pegawai::factory()->create();
    $pangkatAktif = RefPangkat::factory()->create();
    $pangkatTerhapus = RefPangkat::factory()->create();
    $pangkatTerhapus->delete();

    app(UsulanKenaikanPangkatService::class)
        ->shouldNotReceive('createDraft');

    actingAs($actor);

    postJson(route('kenaikan-pangkat.usulan.store'), [
        'pegawai_id' => $pegawaiTerhapus->id,
        'ref_pangkat_tujuan_id' => $pangkatAktif->id,
        'periode_usul_bulan' => 4,
        'periode_usul_tahun' => now()->year,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('pegawai_id');

    postJson(route('kenaikan-pangkat.usulan.store'), [
        'pegawai_id' => $pegawaiAktif->id,
        'ref_pangkat_tujuan_id' => $pangkatTerhapus->id,
        'periode_usul_bulan' => 4,
        'periode_usul_tahun' => now()->year,
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('ref_pangkat_tujuan_id');
});
