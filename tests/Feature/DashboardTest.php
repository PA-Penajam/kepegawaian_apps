<?php

use App\Models\Pegawai;
use App\Services\DashboardStatService;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirectContains(route('sso.login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = Pegawai::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard returns fastStats langsung dan heavyStats sebagai deferred', function () {
    $user = Pegawai::factory()->create();
    $this->actingAs($user);

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('dashboard')
            ->has('fastStats', fn (AssertableInertia $stats) => $stats
                ->has('total_pegawai_aktif')
                ->has('kgb_segera_count')
                ->has('kp_eligible_count')
                ->has('pegawai_baru_bulan_ini')
                ->whereAllType([
                    'total_pegawai_aktif' => 'integer',
                    'kgb_segera_count' => 'integer',
                    'kp_eligible_count' => 'integer',
                    'pegawai_baru_bulan_ini' => 'integer',
                ])
            )
            ->missing('heavyStats')
        );
});

test('distribusi golongan menggunakan query SQL bukan PHP collection', function () {
    $user = Pegawai::factory()->admin()->create();

    DB::enableQueryLog();
    $service = app(DashboardStatService::class);
    $result = $service->getDistribusiGolongan();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toHaveCount(1)
        ->and($result)->toBeArray()
        ->and($result)->toHaveKeys(['I', 'II', 'III', 'IV']);
});

test('distribusi jabatan menggunakan query SQL bukan PHP collection', function () {
    $user = Pegawai::factory()->admin()->create();

    DB::enableQueryLog();
    $service = app(DashboardStatService::class);
    $result = $service->getDistribusiJabatan();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toHaveCount(1)
        ->and($result)->toBeCollection();
});

test('distribusi pendidikan menggunakan query SQL bukan PHP collection', function () {
    $user = Pegawai::factory()->admin()->create();

    DB::enableQueryLog();
    $service = app(DashboardStatService::class);
    $result = $service->getDistribusiPendidikan();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    expect($queries)->toHaveCount(1)
        ->and($result)->toBeCollection();
});
