<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('dashboard returns required statistics', function () {
    $user = User::factory()->create();
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
