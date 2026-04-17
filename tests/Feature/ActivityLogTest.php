<?php

use App\Models\IamApplication;
use App\Models\IamRole;
use App\Models\Pegawai;
use App\Models\RiwayatPangkat;
use Spatie\Activitylog\Models\Activity;

test('activity log ter-create saat pegawai diupdate', function () {
    $admin = Pegawai::factory()->admin()->create();
    $pegawai = Pegawai::factory()->create(['nama_lengkap' => 'Sebelum Update']);

    $this->actingAs($admin);

    $pegawai->update(['nama_lengkap' => 'Sesudah Update']);

    $log = Activity::where('subject_type', Pegawai::class)
        ->where('subject_id', $pegawai->id)
        ->where('description', 'updated')
        ->latest()->first();

    expect($log)->not->toBeNull()
        ->and($log->subject_type)->toBe(Pegawai::class)
        ->and($log->subject_id)->toBe($pegawai->id)
        ->and($log->description)->toBe('updated')
        ->and($log->attribute_changes->get('old')['nama_lengkap'])->toBe('Sebelum Update')
        ->and($log->attribute_changes->get('attributes')['nama_lengkap'])->toBe('Sesudah Update');
});

test('activity log tidak ter-create jika tidak ada field yang berubah', function () {
    $pegawai = Pegawai::factory()->create(['nama_lengkap' => 'Tidak Berubah']);
    $countBefore = Activity::count();

    $pegawai->update(['nama_lengkap' => 'Tidak Berubah']);

    expect(Activity::count())->toBe($countBefore);
});

test('activity log ter-create saat riwayat pangkat dibuat', function () {
    $admin = Pegawai::factory()->admin()->create();
    $pegawai = Pegawai::factory()->create();

    $this->actingAs($admin);

    $riwayat = RiwayatPangkat::factory()->create(['pegawai_id' => $pegawai->id]);

    $log = Activity::where('subject_type', RiwayatPangkat::class)
        ->where('description', 'created')
        ->latest()->first();

    expect($log)->not->toBeNull()
        ->and($log->subject_id)->toBe($riwayat->id);
});

test('activity log ter-create saat iam role diupdate', function () {
    $admin = Pegawai::factory()->admin()->create();
    $app = IamApplication::factory()->create();
    $role = IamRole::factory()->create([
        'iam_application_id' => $app->id,
        'nama' => 'Role Lama',
    ]);

    $this->actingAs($admin);

    $role->update(['nama' => 'Role Baru']);

    $log = Activity::where('subject_type', IamRole::class)
        ->where('description', 'updated')
        ->latest()->first();

    expect($log)->not->toBeNull()
        ->and($log->attribute_changes->get('old')['nama'])->toBe('Role Lama')
        ->and($log->attribute_changes->get('attributes')['nama'])->toBe('Role Baru');
});

test('halaman activity log hanya bisa diakses admin', function () {
    $this->withoutVite();

    $admin = Pegawai::factory()->admin()->create();
    $operator = Pegawai::factory()->operator()->create();
    $viewer = Pegawai::factory()->viewer()->create();

    $this->actingAs($admin)->get('/activity-log')->assertOk();
    $this->actingAs($operator)->get('/activity-log')->assertForbidden();
    $this->actingAs($viewer)->get('/activity-log')->assertForbidden();
});

test('halaman activity log menampilkan daftar aktivitas', function () {
    $this->withoutVite();

    $admin = Pegawai::factory()->admin()->create();
    $pegawai = Pegawai::factory()->create(['nama_lengkap' => 'Target Log']);

    $this->actingAs($admin);
    $pegawai->update(['nama_lengkap' => 'Setelah Update']);

    $this->actingAs($admin)
        ->get('/activity-log')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('activity-log/index')
            ->has('activities.data')
        );
});
