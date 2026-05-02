<?php

use App\Models\Cuti\CutiPengajuan;
use App\Models\Pegawai;
use App\Services\Cuti\SaldoLedgerService;
use Database\Seeders\CutiJenisMasterSeeder;
use Database\Seeders\CutiPermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

beforeEach(function () {
    $this->seed(CutiJenisMasterSeeder::class);
    $this->seed(CutiPermissionSeeder::class);
});

test('store creates pengajuan and redirects to show', function () {
    $user = Pegawai::factory()->admin()->create();

    // Setup alokasi saldo
    app(SaldoLedgerService::class)->kreditAlokasi($user->nip, 'CT', now()->year, 12, 'init');

    actingAs($user);

    $response = post(route('cuti.pengajuan.store'), [
        'jenis_cuti_kode' => 'CT',
        'tanggal_mulai' => now()->addWeeks(2)->toDateString(),
        'tanggal_selesai' => now()->addWeeks(2)->addDays(4)->toDateString(),
        'alasan' => 'Liburan keluarga ke luar kota',
    ]);

    $pengajuan = CutiPengajuan::where('pegawai_nip', $user->nip)->first();

    expect($pengajuan)->not->toBeNull()
        ->and($pengajuan->state->name())->toBe('DIAJUKAN')
        ->and($pengajuan->alasan)->toBe('Liburan keluarga ke luar kota');

    $response->assertRedirect(route('cuti.pengajuan.show', $pengajuan->id));
});

test('store validates cross year pengajuan', function () {
    $user = Pegawai::factory()->admin()->create();

    actingAs($user);

    $response = post(route('cuti.pengajuan.store'), [
        'jenis_cuti_kode' => 'CT',
        'tanggal_mulai' => '2026-12-28',
        'tanggal_selesai' => '2027-01-05',
        'alasan' => 'Libur akhir tahun lintas tahun',
    ]);

    $response->assertSessionHasErrors('tanggal_selesai');
});

test('show renders detail page with pengajuan data', function () {
    $user = Pegawai::factory()->admin()->create();

    $pengajuan = CutiPengajuan::factory()->submitted()->create([
        'pegawai_nip' => $user->nip,
    ]);

    actingAs($user);

    $this->withoutVite();

    $response = get(route('cuti.pengajuan.show', $pengajuan->id));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cuti/pengajuan/show')
            ->has('pengajuan')
            ->where('pengajuan.id', $pengajuan->id)
        );
});

test('create renders form with jenis cuti list', function () {
    $user = Pegawai::factory()->admin()->create();

    actingAs($user);

    $this->withoutVite();

    $response = get(route('cuti.pengajuan.create'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cuti/pengajuan/create')
            ->has('jenisCutiList')
        );
});

test('store validates required fields', function () {
    $user = Pegawai::factory()->admin()->create();

    actingAs($user);

    $response = post(route('cuti.pengajuan.store'), []);

    $response->assertSessionHasErrors([
        'jenis_cuti_kode',
        'tanggal_mulai',
        'tanggal_selesai',
        'alasan',
    ]);
});

test('guests cannot access pengajuan routes', function () {
    get(route('cuti.pengajuan.create'))
        ->assertRedirectContains('login');

    post(route('cuti.pengajuan.store'))
        ->assertRedirectContains('login');
});
