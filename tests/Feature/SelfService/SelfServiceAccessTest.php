<?php

use App\Models\Pegawai;
use App\Models\RiwayatPangkat;
use Carbon\Carbon;
use Database\Seeders\RefJabatanSeeder;
use Database\Seeders\RefPangkatSeeder;
use Database\Seeders\RefUnitKerjaSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\seed;

beforeEach(function (): void {
    seed(RefPangkatSeeder::class);
    seed(RefJabatanSeeder::class);
    seed(RefUnitKerjaSeeder::class);
});

afterEach(function (): void {
    Carbon::setTestNow();
});

it('linked_user_can_access_self_service_index', function (): void {
    Carbon::setTestNow('2026-01-01');

    $pegawai = Pegawai::factory()->viewer()->create();
    RiwayatPangkat::factory()->create([
        'pegawai_id' => $pegawai->id,
        'ref_pangkat_id' => $pegawai->ref_pangkat_id,
        'tmt' => '2024-04-01',
        'is_aktif' => true,
        'masa_kerja_tahun' => 2,
        'masa_kerja_bulan' => 0,
    ]);

    actingAs($pegawai)
        ->get(route('self-service.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('self-service/index')
            ->where('pegawai.id', $pegawai->id)
            ->where('kgbInfo.tanggal_kgb_berikutnya', '2026-04-01')
            ->where('kgbInfo.sisa_hari', 90)
            ->where('kpInfo.tmt_kp_berikutnya', '2028-04-01')
            ->where('kpInfo.periode_usul', 'April 2028')
            ->where('kpInfo.batas_usul', '2028-03-01'),
        );
});

it('linked_user_can_access_self_service_detail', function (): void {
    $pegawai = Pegawai::factory()->viewer()->create();

    actingAs($pegawai)
        ->get(route('self-service.detail'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('self-service/detail')
            ->where('pegawai.id', $pegawai->id),
        );
});

it('viewer_cannot_access_kepegawaian_routes', function (): void {
    $pegawai = Pegawai::factory()->viewer()->create();

    actingAs($pegawai)
        ->get(route('kepegawaian.pegawai.index'))
        ->assertForbidden();
});

it('admin_can_still_access_self_service', function (): void {
    $pegawai = Pegawai::factory()->admin()->create();

    actingAs($pegawai)
        ->get(route('self-service.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('self-service/index')
            ->where('pegawai.id', $pegawai->id),
        );
});

it('self_service_shows_own_pegawai_data_not_others', function (): void {
    $myPegawai = Pegawai::factory()->viewer()->create(['nama_lengkap' => 'Pegawai Saya']);
    Pegawai::factory()->viewer()->create(['nama_lengkap' => 'Pegawai Lain']);

    actingAs($myPegawai)
        ->get(route('self-service.index'))
        ->assertInertia(fn ($page) => $page
            ->component('self-service/index')
            ->where('pegawai.id', $myPegawai->id)
            ->where('pegawai.nama_lengkap', 'Pegawai Saya')
        );
});
