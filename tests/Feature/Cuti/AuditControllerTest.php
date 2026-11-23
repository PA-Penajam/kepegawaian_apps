<?php

use App\Models\Cuti\CutiPengajuan;
use App\Models\Pegawai;
use Database\Seeders\CutiJenisMasterSeeder;
use Database\Seeders\CutiPermissionSeeder;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Activitylog\Models\Activity;

beforeEach(function () {
    $this->seed(CutiJenisMasterSeeder::class);
    $this->seed(CutiPermissionSeeder::class);
    $this->withoutVite();
});

test('model cuti mencatat activity log dengan log_name cuti sedangkan model non-cuti tetap default', function () {
    $pegawai = Pegawai::factory()->create();

    $pengajuan = CutiPengajuan::factory()->create([
        'pegawai_nip' => $pegawai->nip,
        'jenis_cuti_kode' => 'CT',
        'alasan' => 'Alasan awal',
    ]);

    $pengajuan->update(['alasan' => 'Alasan diperbarui']);

    $cutiActivity = Activity::where('subject_type', CutiPengajuan::class)
        ->where('subject_id', $pengajuan->id)
        ->where('description', 'updated')
        ->first();

    $pegawaiActivity = Activity::where('subject_type', Pegawai::class)
        ->where('subject_id', $pegawai->id)
        ->first();

    expect($cutiActivity)->not->toBeNull()
        ->and($cutiActivity->log_name)->toBe('cuti')
        ->and($cutiActivity->attribute_changes->toArray())->toHaveKey('attributes.alasan', 'Alasan diperbarui')
        ->and($cutiActivity->attribute_changes->toArray())->toHaveKey('old.alasan', 'Alasan awal')
        ->and($pegawaiActivity)->not->toBeNull()
        ->and($pegawaiActivity->log_name)->toBe('default');
});

test('AuditController menampilkan log cuti beserta attribute_changes tanpa membocorkan model non-cuti', function () {
    $admin = Pegawai::factory()->admin()->create();
    $pegawai = Pegawai::factory()->create();

    $this->actingAs($admin);

    $pengajuan = CutiPengajuan::factory()->create([
        'pegawai_nip' => $pegawai->nip,
        'jenis_cuti_kode' => 'CT',
        'alasan' => 'Alasan sebelum edit',
    ]);
    $pengajuan->update(['alasan' => 'Alasan sesudah edit']);

    $response = $this->get(route('admin.cuti.audit.index'));

    $response->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cuti/admin/audit')
            ->has('activities.data', fn (Assert $items) => $items
                ->each(fn (Assert $item) => $item
                    ->where('log_name', 'cuti')
                    ->has('attribute_changes')
                    ->etc()
                )
            )
        );
});
