<?php

use App\Models\Keluarga;
use App\Models\Pegawai;
use App\Models\PengajuanPerubahanData;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

it('pegawai dapat mengajukan perubahan profil pribadi sebagai pending', function (): void {
    $pegawai = Pegawai::factory()->viewer()->create([
        'nama_lengkap' => 'Nama Lama',
        'status_perkawinan' => 'belum_kawin',
    ]);

    actingAs($pegawai)
        ->post(route('self-service.pengajuan.store'), [
            'domain' => 'profil_pribadi',
            'aksi' => 'update',
            'target_type' => 'pegawai',
            'target_id' => $pegawai->id,
            'after_payload' => [
                'nama_lengkap' => 'Nama Baru',
                'status_perkawinan' => 'kawin',
            ],
            'lampiran' => [
                UploadedFile::fake()->image('ktp-baru.jpg'),
            ],
        ])
        ->assertRedirect(route('self-service.pengajuan.index'));

    $this->assertDatabaseHas('pengajuan_perubahan_data', [
        'pengaju_id' => $pegawai->id,
        'jenis_pengaju' => 'pegawai',
        'domain' => 'profil_pribadi',
        'aksi' => 'update',
        'status' => 'pending',
        'target_id' => $pegawai->id,
    ]);

    expect(Pegawai::query()->findOrFail($pegawai->id)->nama_lengkap)->toBe('Nama Lama');
});

it('menolak pengajuan baru jika masih ada pending untuk profil pribadi yang sama', function (): void {
    $pegawai = Pegawai::factory()->viewer()->create();

    PengajuanPerubahanData::factory()->create([
        'pengaju_id' => $pegawai->id,
        'jenis_pengaju' => 'pegawai',
        'domain' => 'profil_pribadi',
        'aksi' => 'update',
        'target_type' => 'pegawai',
        'target_id' => $pegawai->id,
        'status' => 'pending',
    ]);

    actingAs($pegawai)
        ->post(route('self-service.pengajuan.store'), [
            'domain' => 'profil_pribadi',
            'aksi' => 'update',
            'target_type' => 'pegawai',
            'target_id' => $pegawai->id,
            'after_payload' => ['nama_lengkap' => 'Nama Kedua'],
            'lampiran' => [UploadedFile::fake()->image('ktp-baru.jpg')],
        ])
        ->assertSessionHasErrors('domain');
});

it('mewajibkan lampiran untuk perubahan identitas utama profil pribadi', function (): void {
    $pegawai = Pegawai::factory()->viewer()->create(['nama_lengkap' => 'Nama Lama']);

    actingAs($pegawai)
        ->post(route('self-service.pengajuan.store'), [
            'domain' => 'profil_pribadi',
            'aksi' => 'update',
            'target_type' => 'pegawai',
            'target_id' => $pegawai->id,
            'after_payload' => ['nama_lengkap' => 'Nama Baru'],
            'lampiran' => [],
        ])
        ->assertSessionHasErrors('lampiran');
});

it('pengaju dapat melihat riwayat dan detail pengajuannya sendiri', function (): void {
    $me = Pegawai::factory()->viewer()->create();

    $pengajuan = PengajuanPerubahanData::factory()->create([
        'pengaju_id' => $me->id,
        'subject_pegawai_id' => $me->id,
        'domain' => 'profil_pribadi',
        'before_payload' => ['nama_lengkap' => 'Nama Lama'],
        'after_payload' => ['nama_lengkap' => 'Nama Baru'],
    ]);

    actingAs($me)
        ->withoutVite()
        ->get(route('self-service.pengajuan.show', $pengajuan))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('self-service/pengajuan/show')
            ->has('diffItems', 1)
            ->where('pengajuan.id', $pengajuan->id)
        );
});

it('pengaju tidak dapat melihat pengajuan milik pegawai lain', function (): void {
    $me = Pegawai::factory()->viewer()->create();
    $other = Pegawai::factory()->viewer()->create();

    $pengajuanOrang = PengajuanPerubahanData::factory()->create([
        'pengaju_id' => $other->id,
        'subject_pegawai_id' => $other->id,
    ]);

    actingAs($me)
        ->withoutVite()
        ->get(route('self-service.pengajuan.show', $pengajuanOrang))
        ->assertNotFound();
});

it('pengaju melihat alasan penolakan dan diff pada riwayatnya sendiri', function (): void {
    $pegawai = Pegawai::factory()->viewer()->create();

    $pengajuan = PengajuanPerubahanData::factory()->create([
        'pengaju_id' => $pegawai->id,
        'subject_pegawai_id' => $pegawai->id,
        'status' => 'rejected',
        'before_payload' => ['nama_lengkap' => 'Nama Lama'],
        'after_payload' => ['nama_lengkap' => 'Nama Baru'],
        'alasan_penolakan' => 'Dokumen tidak sesuai.',
    ]);

    actingAs($pegawai)
        ->withoutVite()
        ->get(route('self-service.pengajuan.show', $pengajuan))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('self-service/pengajuan/show')
            ->where('pengajuan.status', 'rejected')
            ->where('pengajuan.alasan_penolakan', 'Dokumen tidak sesuai.')
            ->where('diffItems.0.field', 'nama_lengkap')
        );
});

// M3: pegawai submit keluarga via endpoint self-service langsung
it('pegawai dapat mengajukan perubahan data keluarga anak sebagai pending', function (): void {
    $pegawai = Pegawai::factory()->viewer()->create();
    $keluarga = Keluarga::factory()->create([
        'pegawai_id' => $pegawai->id,
        'hubungan' => 'Anak',
        'nama' => 'Nama Lama Anak',
    ]);

    actingAs($pegawai)
        ->post(route('self-service.pengajuan.store'), [
            'domain' => 'anak',
            'aksi' => 'update',
            'target_type' => 'keluarga',
            'target_id' => $keluarga->id,
            'after_payload' => [
                'nama' => 'Nama Baru Anak',
                'hubungan' => 'Anak',
            ],
            'lampiran' => [UploadedFile::fake()->image('akta-anak.jpg')],
        ])
        ->assertRedirect(route('self-service.pengajuan.index'));

    $this->assertDatabaseHas('pengajuan_perubahan_data', [
        'pengaju_id' => $pegawai->id,
        'domain' => 'anak',
        'aksi' => 'update',
        'status' => 'pending',
    ]);

    expect($keluarga->fresh()->nama)->toBe('Nama Lama Anak');
});

// M5: flash message setelah submit
it('submit pengajuan memberikan flash message sukses', function (): void {
    $pegawai = Pegawai::factory()->viewer()->create(['nama_lengkap' => 'Nama Lama']);

    actingAs($pegawai)
        ->post(route('self-service.pengajuan.store'), [
            'domain' => 'profil_pribadi',
            'aksi' => 'update',
            'target_type' => 'pegawai',
            'target_id' => $pegawai->id,
            'after_payload' => ['nama_lengkap' => 'Nama Baru'],
            'lampiran' => [UploadedFile::fake()->image('ktp-baru.jpg')],
        ])
        ->assertSessionHas('success');
});

// M2: diff service hanya mengembalikan field yang berubah
it('diff hanya menampilkan field yang berubah dan mengabaikan field tidak berubah', function (): void {
    $pegawai = Pegawai::factory()->viewer()->create();

    $pengajuan = PengajuanPerubahanData::factory()->create([
        'pengaju_id' => $pegawai->id,
        'subject_pegawai_id' => $pegawai->id,
        'before_payload' => ['nama_lengkap' => 'Sama', 'tempat_lahir' => 'Jakarta'],
        'after_payload' => ['nama_lengkap' => 'Sama', 'tempat_lahir' => 'Bandung'],
    ]);

    actingAs($pegawai)
        ->withoutVite()
        ->get(route('self-service.pengajuan.show', $pengajuan))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->has('diffItems', 1)
            ->where('diffItems.0.field', 'tempat_lahir')
        );
});
