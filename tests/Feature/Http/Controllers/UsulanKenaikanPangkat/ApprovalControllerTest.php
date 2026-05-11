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
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Mockery;
use Mockery\MockInterface;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\from;
use function Pest\Laravel\get;
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

function grantApprovalPermission(Pegawai $pegawai, string $slug): void
{
    $app = IamApplication::query()->firstOrCreate(
        ['slug' => 'test-kp-approval-controller'],
        ['nama' => 'KP Approval Controller Test', 'url' => 'https://local.test']
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
