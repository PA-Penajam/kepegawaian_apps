<?php

namespace Tests\Feature\Http\Controllers\UsulanKenaikanPangkat;

use App\Http\Controllers\UsulanKenaikanPangkat\UsulanKenaikanPangkatController;
use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\Pegawai;
use App\Models\RefPangkat;
use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;
use App\Services\UsulanKenaikanPangkat\UsulanKenaikanPangkatService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\from;
use function Pest\Laravel\get;
use function Pest\Laravel\post;
use function Pest\Laravel\withoutVite;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    app()->instance(UsulanKenaikanPangkatService::class, Mockery::mock(UsulanKenaikanPangkatService::class));
    withoutVite();

    Route::middleware('web')->prefix('_test/usulan-kenaikan-pangkat')->name('usulan-kenaikan-pangkat.')->group(function (): void {
        Route::get('/', [UsulanKenaikanPangkatController::class, 'index'])->middleware('auth')->name('index');
        Route::get('/create', [UsulanKenaikanPangkatController::class, 'create'])->middleware('auth')->name('create');
        Route::post('/', [UsulanKenaikanPangkatController::class, 'store'])->middleware('auth')->name('store');
        Route::get('/{usulan}', [UsulanKenaikanPangkatController::class, 'show'])->middleware('auth')->name('show');
    });
    Route::getRoutes()->refreshNameLookups();
});

afterEach(function (): void {
    Mockery::close();
});

it('menampilkan daftar usulan untuk pengguna terotorisasi', function (): void {
    $pegawai = Pegawai::factory()->create(['id' => fake()->uuid()]);
    grantPermission($pegawai, 'kenaikan-pangkat.usulan.view');
    UsulanKenaikanPangkat::factory()->create(['pegawai_id' => $pegawai->id]);

    actingAs($pegawai);

    $response = get('/_test/usulan-kenaikan-pangkat?state=DRAFT');

    $response->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kenaikan-pangkat/usulan/index')
            ->has('usulan')
            ->where('filters.state', 'DRAFT')
        );
});

it('menyimpan draft valid dan redirect ke detail usulan', function (): void {
    $actor = Pegawai::factory()->create();
    $pegawai = Pegawai::factory()->create(['id' => fake()->uuid()]);
    $pangkat = RefPangkat::factory()->create();
    grantPermission($actor, 'kenaikan-pangkat.usulan.create');
    $usulan = UsulanKenaikanPangkat::factory()->make(['id' => (string) str()->ulid()]);

    app(UsulanKenaikanPangkatService::class)
        ->shouldReceive('createDraft')
        ->once()
        ->with(Mockery::on(fn (array $data): bool => $data['pegawai_id'] === $pegawai->id), Mockery::on(fn (Pegawai $user): bool => $user->is($actor)))
        ->andReturn($usulan);

    actingAs($actor);

    $response = post('/_test/usulan-kenaikan-pangkat', [
        'pegawai_id' => $pegawai->id,
        'ref_pangkat_tujuan_id' => $pangkat->id,
        'periode_usul_bulan' => 4,
        'periode_usul_tahun' => now()->year,
        'catatan_pengusul' => 'Layak diusulkan.',
    ]);

    $response->assertRedirect('/_test/usulan-kenaikan-pangkat/'.$usulan->id);
});

it('menolak payload store tidak valid', function (): void {
    $actor = Pegawai::factory()->create();
    grantPermission($actor, 'kenaikan-pangkat.usulan.create');

    actingAs($actor);

    $response = from('/_test/usulan-kenaikan-pangkat/create')->post('/_test/usulan-kenaikan-pangkat', []);

    $response->assertSessionHasErrors(['pegawai_id', 'ref_pangkat_tujuan_id', 'periode_usul_bulan', 'periode_usul_tahun']);
});

it('mengembalikan 403 saat policy menolak akses', function (): void {
    $pegawai = Pegawai::factory()->create();

    actingAs($pegawai);

    $response = get('/_test/usulan-kenaikan-pangkat');

    $response->assertForbidden();
});

it('mengalihkan guest ke login', function (): void {
    $response = get('/_test/usulan-kenaikan-pangkat');

    $response->assertRedirect();
});

function grantPermission(Pegawai $pegawai, string $slug): void
{
    $app = IamApplication::query()->firstOrCreate(
        ['slug' => 'test-kp-controller'],
        ['nama' => 'KP Controller Test', 'url' => 'https://local.test']
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
