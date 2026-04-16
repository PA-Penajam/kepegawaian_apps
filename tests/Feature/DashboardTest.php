<?php

use App\Models\Pegawai;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = Pegawai::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard returns required statistics', function () {
    $user = Pegawai::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();

    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('dashboard')
        ->has('stats', fn (AssertableInertia $page) => $page
            ->has('total_pegawai_aktif')
            ->has('distribusi_golongan')
            ->has('distribusi_unit_kerja')
            ->has('distribusi_jenis_kelamin')
            ->has('kgb_segera_count')
            ->has('kp_eligible_count')
            ->has('distribusi_jabatan')
            ->has('distribusi_pendidikan')
            ->has('pegawai_baru_bulan_ini')
        )
    );
});

test('distribusi golongan menggunakan query SQL bukan PHP collection', function () {
    // Buat beberapa pegawai dengan pangkat berbeda golongan
    $user = Pegawai::factory()->admin()->create();

    DB::enableQueryLog();
    $service = app(\App\Services\DashboardStatService::class);
    $result = $service->getDistribusiGolongan();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // Hanya 1 query untuk distribusi golongan
    expect($queries)->toHaveCount(1)
        ->and($result)->toBeArray()
        ->and($result)->toHaveKeys(['I', 'II', 'III', 'IV']);
});

test('distribusi jabatan menggunakan query SQL bukan PHP collection', function () {
    $user = Pegawai::factory()->admin()->create();

    DB::enableQueryLog();
    $service = app(\App\Services\DashboardStatService::class);
    $result = $service->getDistribusiJabatan();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // Hanya 1 query untuk distribusi jabatan
    expect($queries)->toHaveCount(1)
        ->and($result)->toBeCollection();
});

test('distribusi pendidikan menggunakan query SQL bukan PHP collection', function () {
    $user = Pegawai::factory()->admin()->create();

    DB::enableQueryLog();
    $service = app(\App\Services\DashboardStatService::class);
    $result = $service->getDistribusiPendidikan();
    $queries = DB::getQueryLog();
    DB::disableQueryLog();

    // Hanya 1 query untuk distribusi pendidikan
    expect($queries)->toHaveCount(1)
        ->and($result)->toBeCollection();
});
