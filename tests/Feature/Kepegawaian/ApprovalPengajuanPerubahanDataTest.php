<?php

use App\Enums\StatusPengajuanPerubahanData;
use App\Models\Pegawai;
use App\Models\PengajuanPerubahanData;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\get;
use function Pest\Laravel\post;

it('validator dapat melihat inbox pengajuan pending global', function (): void {
    $validator = Pegawai::factory()->validator()->create();

    PengajuanPerubahanData::factory()->count(2)->create(['status' => 'pending']);
    PengajuanPerubahanData::factory()->create(['status' => 'approved']);

    actingAs($validator)
        ->withoutVite()
        ->get(route('kepegawaian.pengajuan.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kepegawaian/pengajuan/index')
            ->etc()
            ->has('pengajuanList.data', 2)
        );
});

it('validator dapat approve dan perubahan langsung ditulis ke data asli', function (): void {
    $validator = Pegawai::factory()->validator()->create();
    $pegawai = Pegawai::factory()->create(['nama_lengkap' => 'Nama Lama']);

    $pengajuan = PengajuanPerubahanData::factory()->create([
        'pengaju_id' => $pegawai->id,
        'subject_pegawai_id' => $pegawai->id,
        'domain' => 'profil_pribadi',
        'aksi' => 'update',
        'target_type' => 'pegawai',
        'target_id' => $pegawai->id,
        'status' => 'pending',
        'before_payload' => ['nama_lengkap' => 'Nama Lama'],
        'after_payload' => ['nama_lengkap' => 'Nama Baru'],
    ]);

    actingAs($validator)
        ->post(route('kepegawaian.pengajuan.approve', $pengajuan))
        ->assertRedirect(route('kepegawaian.pengajuan.show', $pengajuan));

    expect($pegawai->fresh()->nama_lengkap)->toBe('Nama Baru');

    $this->assertDatabaseHas('pengajuan_perubahan_data', [
        'id' => $pengajuan->id,
        'status' => 'approved',
        'validator_id' => $validator->id,
    ]);
});

it('validator wajib mengisi alasan saat reject', function (): void {
    $validator = Pegawai::factory()->validator()->create();
    $pengajuan = PengajuanPerubahanData::factory()->create(['status' => 'pending']);

    actingAs($validator)
        ->post(route('kepegawaian.pengajuan.reject', $pengajuan), [
            'alasan_penolakan' => '',
        ])
        ->assertSessionHasErrors('alasan_penolakan');
});

it('validator dapat reject dengan alasan dan data asli tidak berubah', function (): void {
    $validator = Pegawai::factory()->validator()->create();
    $pegawai = Pegawai::factory()->create(['nama_lengkap' => 'Nama Lama']);

    $pengajuan = PengajuanPerubahanData::factory()->create([
        'pengaju_id' => $pegawai->id,
        'subject_pegawai_id' => $pegawai->id,
        'domain' => 'profil_pribadi',
        'aksi' => 'update',
        'target_type' => 'pegawai',
        'target_id' => $pegawai->id,
        'status' => 'pending',
        'before_payload' => ['nama_lengkap' => 'Nama Lama'],
        'after_payload' => ['nama_lengkap' => 'Nama Baru'],
    ]);

    actingAs($validator)
        ->post(route('kepegawaian.pengajuan.reject', $pengajuan), [
            'alasan_penolakan' => 'Dokumen tidak lengkap.',
        ])
        ->assertRedirect(route('kepegawaian.pengajuan.show', $pengajuan));

    expect($pegawai->fresh()->nama_lengkap)->toBe('Nama Lama');

    $this->assertDatabaseHas('pengajuan_perubahan_data', [
        'id' => $pengajuan->id,
        'status' => 'rejected',
        'validator_id' => $validator->id,
        'alasan_penolakan' => 'Dokumen tidak lengkap.',
    ]);
});

it('validator dapat melihat detail diff pengajuan', function (): void {
    $validator = Pegawai::factory()->validator()->create();

    $pengajuan = PengajuanPerubahanData::factory()->create([
        'status' => 'pending',
        'before_payload' => ['nama_lengkap' => 'Nama Lama'],
        'after_payload' => ['nama_lengkap' => 'Nama Baru'],
    ]);

    actingAs($validator)
        ->withoutVite()
        ->get(route('kepegawaian.pengajuan.show', $pengajuan))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kepegawaian/pengajuan/show')
            ->etc()
            ->has('diffItems', 1)
            ->where('diffItems.0.field', 'nama_lengkap')
        );
});

it('non-validator tidak dapat mengakses inbox pengajuan', function (): void {
    $pegawai = Pegawai::factory()->viewer()->create();

    actingAs($pegawai)
        ->get(route('kepegawaian.pengajuan.index'))
        ->assertForbidden();
});

it('validator melihat diff item dan counter pending pada response inertia', function (): void {
    $validator = Pegawai::factory()->validator()->create();

    $pengajuan = PengajuanPerubahanData::factory()->create([
        'status' => 'pending',
        'before_payload' => ['nama_lengkap' => 'Nama Lama'],
        'after_payload' => ['nama_lengkap' => 'Nama Baru'],
    ]);

    actingAs($validator)
        ->withoutVite()
        ->get(route('kepegawaian.pengajuan.show', $pengajuan))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kepegawaian/pengajuan/show')
            ->etc()
            ->has('diffItems', 1)
            ->where('diffItems.0.field', 'nama_lengkap')
            ->where('auth.user.pending_pengajuan_count', 1)
        );
});
