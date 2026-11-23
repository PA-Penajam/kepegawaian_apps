<?php

use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\Keluarga;
use App\Models\Pegawai;
use App\Models\PengajuanPerubahanData;
use App\Services\PengajuanPerubahanData\ApprovePengajuanPerubahanDataService;
use App\Services\PengajuanPerubahanData\RejectPengajuanPerubahanDataService;
use Inertia\Testing\AssertableInertia as Assert;

use function Pest\Laravel\actingAs;

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

// C1: bug operator precedence — validator tidak boleh approve pengajuan yang bukan pending
it('validator tidak dapat approve pengajuan yang sudah approved', function (): void {
    $validator = Pegawai::factory()->validator()->create();
    $pegawai = Pegawai::factory()->create(['nama_lengkap' => 'Nama Lama']);

    $pengajuan = PengajuanPerubahanData::factory()->create([
        'subject_pegawai_id' => $pegawai->id,
        'target_type' => 'pegawai',
        'target_id' => $pegawai->id,
        'status' => 'approved',
        'after_payload' => ['nama_lengkap' => 'Nama Baru'],
    ]);

    actingAs($validator)
        ->post(route('kepegawaian.pengajuan.approve', $pengajuan))
        ->assertForbidden();
});

it('validator tidak dapat reject pengajuan yang sudah rejected', function (): void {
    $validator = Pegawai::factory()->validator()->create();

    $pengajuan = PengajuanPerubahanData::factory()->create([
        'status' => 'rejected',
        'alasan_penolakan' => 'Alasan sebelumnya.',
    ]);

    actingAs($validator)
        ->post(route('kepegawaian.pengajuan.reject', $pengajuan), [
            'alasan_penolakan' => 'Mencoba reject ulang yang sudah rejected.',
        ])
        ->assertForbidden();
});

// M5: flash message setelah approve dan reject
it('approve memberikan flash message sukses', function (): void {
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
        ->assertSessionHas('success');
});

it('reject memberikan flash message sukses', function (): void {
    $validator = Pegawai::factory()->validator()->create();

    $pengajuan = PengajuanPerubahanData::factory()->create(['status' => 'pending']);

    actingAs($validator)
        ->post(route('kepegawaian.pengajuan.reject', $pengajuan), [
            'alasan_penolakan' => 'Dokumen tidak memenuhi persyaratan.',
        ])
        ->assertSessionHas('success');
});

it('service approve dan reject menolak eksekusi ganda pada pengajuan yang bukan pending', function (): void {
    $validator = Pegawai::factory()->validator()->create();
    $pegawai = Pegawai::factory()->create(['nama_lengkap' => 'Nama Awal']);

    $pengajuan = PengajuanPerubahanData::factory()->create([
        'pengaju_id' => $pegawai->id,
        'subject_pegawai_id' => $pegawai->id,
        'domain' => 'profil_pribadi',
        'aksi' => 'update',
        'target_type' => 'pegawai',
        'target_id' => $pegawai->id,
        'status' => 'pending',
        'after_payload' => ['nama_lengkap' => 'Nama Diubah', 'nik' => '9999999999999999'],
    ]);

    $approveService = app(ApprovePengajuanPerubahanDataService::class);
    $rejectService = app(RejectPengajuanPerubahanDataService::class);

    $approveService->handle($pengajuan, $validator);

    expect($pegawai->fresh()->nama_lengkap)->toBe('Nama Diubah');
    expect(fn () => $approveService->handle($pengajuan->fresh(), $validator))
        ->toThrow(DomainException::class);
    expect(fn () => $rejectService->handle($pengajuan->fresh(), $validator, 'Alasan'))
        ->toThrow(DomainException::class);
});

it('approve menolak mutasi keluarga milik pegawai berbeda dari subject_pegawai_id', function (): void {
    $validator = Pegawai::factory()->validator()->create();
    $pegawaiA = Pegawai::factory()->create();
    $pegawaiB = Pegawai::factory()->create();
    $keluargaB = Keluarga::factory()->create([
        'pegawai_id' => $pegawaiB->id,
        'nama' => 'Keluarga B Asli',
    ]);

    $pengajuan = PengajuanPerubahanData::factory()->create([
        'pengaju_id' => $pegawaiA->id,
        'subject_pegawai_id' => $pegawaiA->id,
        'domain' => 'orang_tua',
        'aksi' => 'update',
        'target_type' => 'keluarga',
        'target_id' => $keluargaB->id,
        'status' => 'pending',
        'after_payload' => ['nama' => 'Dibajak'],
    ]);

    $approveService = app(ApprovePengajuanPerubahanDataService::class);

    expect(fn () => $approveService->handle($pengajuan, $validator))
        ->toThrow(DomainException::class);
    expect($keluargaB->fresh()->nama)->toBe('Keluarga B Asli');
});

it('approve menolak mutasi profil_pribadi lintas pegawai ketika target_id berbeda dari subject_pegawai_id', function (): void {
    $validator = Pegawai::factory()->validator()->create();
    $pegawaiA = Pegawai::factory()->create(['nama_lengkap' => 'Pegawai A Asli']);
    $pegawaiB = Pegawai::factory()->create(['nama_lengkap' => 'Pegawai B Asli']);

    $pengajuan = PengajuanPerubahanData::factory()->create([
        'pengaju_id' => $pegawaiA->id,
        'subject_pegawai_id' => $pegawaiA->id,
        'domain' => 'profil_pribadi',
        'aksi' => 'update',
        'target_type' => 'pegawai',
        'target_id' => $pegawaiB->id,
        'status' => 'pending',
        'after_payload' => ['nama_lengkap' => 'Nama Dibajak Oleh A'],
    ]);

    $approveService = app(ApprovePengajuanPerubahanDataService::class);

    expect(fn () => $approveService->handle($pengajuan, $validator))
        ->toThrow(DomainException::class, 'Target pegawai tidak sesuai dengan pegawai subjek pengajuan.');

    expect($pegawaiA->fresh()->nama_lengkap)->toBe('Pegawai A Asli');
    expect($pegawaiB->fresh()->nama_lengkap)->toBe('Pegawai B Asli');
    expect($pengajuan->fresh()->status->value)->toBe('pending');
});

it('menolak self-approve dan self-reject ketika validator adalah pengaju atau subjek pengajuan perubahan data', function (): void {
    $app = IamApplication::query()->firstOrCreate(
        ['slug' => (string) config('iam.app_slug', 'kepegawaian')],
        ['nama' => 'Kepegawaian', 'url' => (string) config('app.url')]
    );
    $validatePermission = IamPermission::query()->firstOrCreate(
        ['slug' => 'pengajuan-perubahan.validate', 'iam_application_id' => $app->id],
        ['nama' => 'Validasi Pengajuan Perubahan']
    );
    $customRole = IamRole::query()->create([
        'iam_application_id' => $app->id,
        'nama' => 'Operator Validator Gabungan',
        'slug' => 'operator-validator-gabungan',
    ]);
    $customRole->permissions()->sync([$validatePermission->id]);

    $aktor = Pegawai::factory()->operator()->create(['nama_lengkap' => 'Nama Asli Aktor']);
    $aktor->iamRoles()->syncWithoutDetaching([$customRole->id]);

    $pegawaiLain = Pegawai::factory()->create(['nama_lengkap' => 'Nama Asli Lain']);

    // Kasus 1: pengaju_id === aktor->id sekaligus subject_pegawai_id === aktor->id
    $pengajuanSelf = PengajuanPerubahanData::factory()->create([
        'pengaju_id' => $aktor->id,
        'subject_pegawai_id' => $aktor->id,
        'domain' => 'profil_pribadi',
        'aksi' => 'update',
        'target_type' => 'pegawai',
        'target_id' => $aktor->id,
        'status' => 'pending',
        'before_payload' => ['nama_lengkap' => 'Nama Asli Aktor'],
        'after_payload' => ['nama_lengkap' => 'Nama Self Approve'],
    ]);

    actingAs($aktor)
        ->post(route('kepegawaian.pengajuan.approve', $pengajuanSelf))
        ->assertForbidden();

    actingAs($aktor)
        ->post(route('kepegawaian.pengajuan.reject', $pengajuanSelf), [
            'alasan_penolakan' => 'Mencoba self reject pengajuan sendiri.',
        ])
        ->assertForbidden();

    // Kasus 2: diajukan operator lain namun subject_pegawai_id === aktor->id
    $pengajuanSubjectSelf = PengajuanPerubahanData::factory()->create([
        'pengaju_id' => $pegawaiLain->id,
        'subject_pegawai_id' => $aktor->id,
        'domain' => 'profil_pribadi',
        'aksi' => 'update',
        'target_type' => 'pegawai',
        'target_id' => $aktor->id,
        'status' => 'pending',
        'before_payload' => ['nama_lengkap' => 'Nama Asli Aktor'],
        'after_payload' => ['nama_lengkap' => 'Nama Subject Approve'],
    ]);

    actingAs($aktor)
        ->post(route('kepegawaian.pengajuan.approve', $pengajuanSubjectSelf))
        ->assertForbidden();

    actingAs($aktor)
        ->post(route('kepegawaian.pengajuan.reject', $pengajuanSubjectSelf), [
            'alasan_penolakan' => 'Mencoba self reject atas subjek diri sendiri.',
        ])
        ->assertForbidden();

    expect(fn () => app(ApprovePengajuanPerubahanDataService::class)->handle($pengajuanSelf->fresh(), $aktor))
        ->toThrow(DomainException::class);
    expect(fn () => app(RejectPengajuanPerubahanDataService::class)->handle($pengajuanSubjectSelf->fresh(), $aktor, 'Self reject via service'))
        ->toThrow(DomainException::class);

    expect($aktor->fresh()->nama_lengkap)->toBe('Nama Asli Aktor')
        ->and($pengajuanSelf->fresh()->status->value)->toBe('pending')
        ->and($pengajuanSubjectSelf->fresh()->status->value)->toBe('pending');
});

it('inbox pengajuan mengembalikan metadata paginasi dan kontrak props nomor_pengajuan serta status lengkap', function (): void {
    $validator = Pegawai::factory()->validator()->create();
    PengajuanPerubahanData::factory()->count(12)->create(['status' => 'pending']);

    actingAs($validator)
        ->withoutVite()
        ->get(route('kepegawaian.pengajuan.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('kepegawaian/pengajuan/index')
            ->where('pengajuanList.total', 12)
            ->where('pengajuanList.last_page', 2)
            ->has('pengajuanList.data', 10)
            ->has('pengajuanList.links')
            ->where('pengajuanList.data.0.status', 'pending')
            ->has('pengajuanList.data.0.nomor_pengajuan')
            ->has('pengajuanList.data.0.pengaju.id')
        );

    actingAs($validator)
        ->withoutVite()
        ->get(route('kepegawaian.pengajuan.index', ['page' => 2]))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('pengajuanList.current_page', 2)
            ->has('pengajuanList.data', 2)
        );
});

it('approve dan reject menangani DomainException konkurensi sebagai validation error 422 bukan 500', function (): void {
    $validator = Pegawai::factory()->validator()->create();
    $pegawai = Pegawai::factory()->create(['nama_lengkap' => 'Nama Awal']);

    $pengajuanApprove = PengajuanPerubahanData::factory()->create([
        'pengaju_id' => $pegawai->id,
        'subject_pegawai_id' => $pegawai->id,
        'domain' => 'profil_pribadi',
        'aksi' => 'update',
        'target_type' => 'pegawai',
        'target_id' => $pegawai->id,
        'status' => 'pending',
        'after_payload' => ['nama_lengkap' => 'Nama Baru'],
    ]);

    $approveService = Mockery::mock(ApprovePengajuanPerubahanDataService::class);
    $approveService->shouldReceive('handle')
        ->once()
        ->andThrow(new DomainException('Pengajuan perubahan data sudah diproses sebelumnya.'));
    app()->instance(ApprovePengajuanPerubahanDataService::class, $approveService);

    actingAs($validator)
        ->postJson(route('kepegawaian.pengajuan.approve', $pengajuanApprove))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('pengajuan');

    $pengajuanReject = PengajuanPerubahanData::factory()->create([
        'pengaju_id' => $pegawai->id,
        'subject_pegawai_id' => $pegawai->id,
        'status' => 'pending',
    ]);

    $rejectService = Mockery::mock(RejectPengajuanPerubahanDataService::class);
    $rejectService->shouldReceive('handle')
        ->once()
        ->andThrow(new DomainException('Pengajuan perubahan data sudah diproses sebelumnya.'));
    app()->instance(RejectPengajuanPerubahanDataService::class, $rejectService);

    actingAs($validator)
        ->postJson(route('kepegawaian.pengajuan.reject', $pengajuanReject), [
            'alasan_penolakan' => 'Ditolak saat kondisi stale concurrent.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('pengajuan');
});

it('approve menolak konflik email unik terhadap pegawai aktif maupun soft-deleted dengan 422', function (): void {
    $validator = Pegawai::factory()->validator()->create();
    $pegawaiA = Pegawai::factory()->create(['email' => 'asal-a@test.local']);
    Pegawai::factory()->create(['email' => 'bentrok-aktif@test.local']);

    $pegawaiSoftDeleted = Pegawai::factory()->create(['email' => 'bentrok-arsip@test.local']);
    $pegawaiSoftDeleted->delete();

    $pengajuanAktif = PengajuanPerubahanData::factory()->create([
        'pengaju_id' => $pegawaiA->id,
        'subject_pegawai_id' => $pegawaiA->id,
        'domain' => 'profil_pribadi',
        'aksi' => 'update',
        'target_type' => 'pegawai',
        'target_id' => $pegawaiA->id,
        'status' => 'pending',
        'before_payload' => ['email' => 'asal-a@test.local'],
        'after_payload' => ['email' => 'bentrok-aktif@test.local'],
    ]);

    actingAs($validator)
        ->postJson(route('kepegawaian.pengajuan.approve', $pengajuanAktif))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('pengajuan');

    expect($pegawaiA->fresh()->email)->toBe('asal-a@test.local')
        ->and($pengajuanAktif->fresh()->status->value)->toBe('pending');

    $pengajuanArsip = PengajuanPerubahanData::factory()->create([
        'pengaju_id' => $pegawaiA->id,
        'subject_pegawai_id' => $pegawaiA->id,
        'domain' => 'profil_pribadi',
        'aksi' => 'update',
        'target_type' => 'pegawai',
        'target_id' => $pegawaiA->id,
        'status' => 'pending',
        'before_payload' => ['email' => 'asal-a@test.local'],
        'after_payload' => ['email' => 'bentrok-arsip@test.local'],
    ]);

    actingAs($validator)
        ->postJson(route('kepegawaian.pengajuan.approve', $pengajuanArsip))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('pengajuan');

    expect($pegawaiA->fresh()->email)->toBe('asal-a@test.local')
        ->and($pengajuanArsip->fresh()->status->value)->toBe('pending');
});
