<?php

use App\Models\Pegawai;
use Illuminate\Http\UploadedFile;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\post;

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

    \App\Models\PengajuanPerubahanData::factory()->create([
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
