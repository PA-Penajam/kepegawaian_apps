<?php

use App\Enums\StatusSuratDinas;
use App\Models\Cuti\CutiPengajuan;
use App\Models\Pegawai;
use App\Models\SuratDinas\SuratDinasRequest;
use App\Services\SuratDinas\Contracts\PenomoranSuratGateway;
use App\Services\SuratDinas\FakePenomoranGateway;
use App\Services\SuratDinas\SuratDinasRequestService;
use Database\Seeders\CutiJenisMasterSeeder;

beforeEach(function () {
    $this->seed(CutiJenisMasterSeeder::class);
    $this->app->instance(PenomoranSuratGateway::class, new FakePenomoranGateway);
});

test('SuratDinasRequestService mengelola siklus status dari BelumTerkirim sampai Terkirim dan Dibatalkan', function () {
    $pegawai = Pegawai::factory()->create();
    $pengajuan = CutiPengajuan::factory()->approved()->create([
        'pegawai_nip' => $pegawai->nip,
        'jenis_cuti_kode' => 'CT',
    ]);

    $service = app(SuratDinasRequestService::class);

    $request = $service->createForSuratable(
        suratable: $pengajuan,
        jenis: 'surat_izin_cuti',
        klasifikasi: 'KP5.3',
        penandatangan: 'ketua',
        documentType: 'surat_dinas',
        perihal: 'Surat Izin Cuti Tahunan',
        tujuanNama: $pegawai->nama_lengkap,
        tujuanInstansi: 'Pengadilan Agama Penajam',
        requestedByNip: $pegawai->nip,
    );

    expect($request->status)->toBe(StatusSuratDinas::BelumTerkirim);

    $service->registerToGateway($request);
    $request->refresh();
    expect($request->status)->toBe(StatusSuratDinas::MenungguNomor)
        ->and($request->sisukma_public_id)->not->toBeEmpty()
        ->and($request->nomor_agenda)->not->toBeEmpty();

    $service->applyRevisionRequested($request, 'Perbaiki penulisan gelar');
    $request->refresh();
    expect($request->status)->toBe(StatusSuratDinas::PerluPerbaikan)
        ->and($request->catatan_revisi)->toBe('Perbaiki penulisan gelar');

    $service->resubmit($request, [
        'perihal' => 'Surat Izin Cuti Tahunan (Perbaikan)',
        'penandatangan' => 'sekretaris',
    ]);
    $request->refresh();
    expect($request->status)->toBe(StatusSuratDinas::MenungguNomor)
        ->and($request->perihal)->toBe('Surat Izin Cuti Tahunan (Perbaikan)')
        ->and($request->penandatangan)->toBe('sekretaris');

    $service->applyNumberIssued($request, '12/SEK.PA.W17-A8/KP5.3/XI/2026', '1/2026');
    $request->refresh();
    expect($request->status)->toBe(StatusSuratDinas::NomorTerbit)
        ->and($request->nomor_surat)->toBe('12/SEK.PA.W17-A8/KP5.3/XI/2026');

    $checksum = str_repeat('a', 64);
    $service->applyLetterAccepted($request, $checksum, 4096);
    $request->refresh();
    expect($request->status)->toBe(StatusSuratDinas::FinalTersedia)
        ->and($request->final_checksum)->toBe($checksum)
        ->and($request->final_size_bytes)->toBe(4096)
        ->and($request->final_uploaded_at)->not->toBeNull();

    $service->applySent($request);
    expect($request->fresh()->status)->toBe(StatusSuratDinas::Terkirim);
});

test('SuratDinasRequestService menandai Dibatalkan saat voided atau cancel sebelum nomor terbit', function () {
    $pegawai = Pegawai::factory()->create();
    $pengajuan = CutiPengajuan::factory()->approved()->create([
        'pegawai_nip' => $pegawai->nip,
        'jenis_cuti_kode' => 'CT',
    ]);

    $service = app(SuratDinasRequestService::class);
    $request = $service->createForSuratable(
        suratable: $pengajuan,
        jenis: 'surat_izin_cuti',
        klasifikasi: 'KP5.3',
        penandatangan: 'ketua',
        documentType: 'surat_dinas',
        perihal: 'Surat Izin Cuti',
        tujuanNama: $pegawai->nama_lengkap,
        tujuanInstansi: 'PA Penajam',
        requestedByNip: $pegawai->nip,
    );

    $service->registerToGateway($request);
    $cancelled = $service->cancelIfUnnumbered($request->fresh(), 'Cuti dicabut');

    expect($cancelled)->toBeTrue()
        ->and($request->fresh()->status)->toBe(StatusSuratDinas::Dibatalkan)
        ->and($request->fresh()->alasan_void)->toBe('Cuti dicabut');

    $numbered = SuratDinasRequest::factory()->nomorTerbit()->create([
        'suratable_type' => $pengajuan->getMorphClass(),
        'suratable_id' => $pengajuan->id,
    ]);
    $cancelledNumbered = $service->cancelIfUnnumbered($numbered, 'Cuti dicabut');
    expect($cancelledNumbered)->toBeFalse()
        ->and($numbered->fresh()->status)->toBe(StatusSuratDinas::NomorTerbit);
});
