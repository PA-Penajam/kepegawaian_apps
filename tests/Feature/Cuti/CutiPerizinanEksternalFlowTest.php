<?php

use App\Enums\JenisJabatan;
use App\Exceptions\Cuti\OverlapPengajuanException;
use App\Models\Cuti\CutiAlokasiTahunan;
use App\Models\Cuti\CutiEvent;
use App\Models\Cuti\CutiKewenangan;
use App\Models\Cuti\CutiPengajuan;
use App\Models\Cuti\CutiPerizinanEksternal;
use App\Models\Cuti\CutiSaldoLedger;
use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\IamUserRole;
use App\Models\Pegawai;
use App\Models\RefJabatan;
use App\Models\SuratDinas\SuratDinasRequest;
use App\Services\Cuti\PengajuanCutiService;
use App\Services\Cuti\SaldoLedgerService;
use App\Services\Cuti\WorkflowService;
use App\Services\SuratDinas\Contracts\PenomoranSuratGateway;
use App\Services\SuratDinas\FakePenomoranGateway;
use Database\Seeders\CutiJenisMasterSeeder;
use Database\Seeders\CutiPermissionSeeder;

beforeEach(function () {
    $this->seed(CutiJenisMasterSeeder::class);
    $this->seed(CutiPermissionSeeder::class);
});

function buatPengajuanDisetujuiAtasanEksternal(string $jenisKode = 'CT'): array
{
    $jabatan = RefJabatan::factory()->create(['jenis_jabatan' => JenisJabatan::Fungsional]);
    $pemohon = Pegawai::factory()->create(['ref_jabatan_id' => $jabatan->id]);
    $pejabat = Pegawai::factory()->admin()->create();

    $app = IamApplication::where('slug', 'kepegawaian')->first();
    $rolePemohon = IamRole::firstOrCreate(
        ['iam_application_id' => $app->id, 'slug' => 'pemohon-eksternal-test'],
        ['nama' => 'Pemohon Test', 'is_system' => false],
    );
    $viewOwnPerm = IamPermission::where('slug', 'cuti.pengajuan.view-own')->first();
    if ($viewOwnPerm) {
        $rolePemohon->permissions()->syncWithoutDetaching([$viewOwnPerm->id]);
    }
    IamUserRole::firstOrCreate(
        ['user_id' => $pemohon->id, 'iam_role_id' => $rolePemohon->id],
        ['assigned_at' => now()],
    );

    $tahun = now()->year;
    if ($jenisKode === 'CT') {
        CutiAlokasiTahunan::create([
            'pegawai_nip' => $pemohon->nip,
            'jenis_cuti_kode' => 'CT',
            'tahun_hak' => $tahun,
            'hak_awal' => 12,
        ]);
        CutiSaldoLedger::create([
            'pegawai_nip' => $pemohon->nip,
            'jenis_cuti_kode' => 'CT',
            'tahun_hak' => $tahun,
            'jenis_transaksi' => 'kredit',
            'jumlah_hari' => 12,
            'aktor_pegawai_nip' => $pemohon->nip,
        ]);
    }

    $pengajuan = CutiPengajuan::factory()->create([
        'pegawai_nip' => $pemohon->nip,
        'jenis_cuti_kode' => $jenisKode,
        'tanggal_mulai' => now()->addWeeks(2)->toDateString(),
        'tanggal_selesai' => now()->addWeeks(2)->addDays(4)->toDateString(),
        'jumlah_hari_kerja' => 3,
        'state' => 'DISETUJUI_ATASAN',
        'pejabat_berwenang_current_nip' => $pejabat->nip,
        'petugas_kepegawaian_current_nip' => $pejabat->nip,
    ]);

    if ($jenisKode === 'CT') {
        app(SaldoLedgerService::class)->debitPendingFifo($pengajuan);
    }

    return [$pemohon, $pejabat, $pengajuan];
}

test('approvePejabat bercabang ke DITERUSKAN_INSTANSI_ATASAN tanpa SuratDinasRequest, menjaga saldo pending, dan memblokir overlap', function () {
    $gateway = new FakePenomoranGateway;
    $this->app->instance(PenomoranSuratGateway::class, $gateway);

    CutiKewenangan::create([
        'jenis_cuti_kode' => 'CT',
        'jenis_jabatan' => JenisJabatan::Fungsional->value,
        'pemberi_izin' => 'pta',
        'instansi_nama' => 'Pengadilan Tinggi Agama Samarinda',
        'nama_aplikasi' => 'SIKEP PTA',
        'url_aplikasi' => 'https://pta.example.test',
        'aktif' => true,
    ]);

    [$pemohon, $pejabat, $pengajuan] = buatPengajuanDisetujuiAtasanEksternal('CT');

    app(WorkflowService::class)->approvePejabat($pengajuan->id, $pejabat, 'Teruskan ke PTA');

    $fresh = $pengajuan->fresh();
    expect($fresh->state->name())->toBe('DITERUSKAN_INSTANSI_ATASAN')
        ->and(SuratDinasRequest::query()->count())->toBe(0)
        ->and($gateway->registered)->toBeEmpty();

    $ext = CutiPerizinanEksternal::query()->where('pengajuan_id', $pengajuan->id)->first();
    expect($ext)->not->toBeNull()
        ->and($ext->pemberi_izin)->toBe('pta')
        ->and($ext->instansi_nama)->toBe('Pengadilan Tinggi Agama Samarinda');

    expect(CutiEvent::query()->where('aggregate_id', $pengajuan->id)->where('event_type', 'cuti.diteruskan')->exists())->toBeTrue();

    // Saldo tetap debit_pending (belum debit_confirmed)
    expect(CutiSaldoLedger::query()
        ->where('pengajuan_id', $pengajuan->id)
        ->where('jenis_transaksi', 'debit_pending')
        ->exists())->toBeTrue()
        ->and(CutiSaldoLedger::query()
            ->where('pengajuan_id', $pengajuan->id)
            ->where('jenis_transaksi', 'debit_confirmed')
            ->exists())->toBeFalse();

    // Overlap dengan pengajuan DITERUSKAN_INSTANSI_ATASAN harus ditolak
    expect(fn () => app(PengajuanCutiService::class)->submit([
        'pegawai_nip' => $pemohon->nip,
        'jenis_cuti_kode' => 'CT',
        'tanggal_mulai' => $pengajuan->tanggal_mulai->toDateString(),
        'tanggal_selesai' => $pengajuan->tanggal_selesai->toDateString(),
        'alasan' => 'Cuti tumpang tindih',
    ]))->toThrow(OverlapPengajuanException::class);
});

test('petugas mencatat registrasi PTA/MA dan keputusan disetujui dengan tautan Surat Masuk SiSukma', function () {
    $gateway = new FakePenomoranGateway;
    $this->app->instance(PenomoranSuratGateway::class, $gateway);

    CutiKewenangan::create([
        'jenis_cuti_kode' => 'CT',
        'jenis_jabatan' => JenisJabatan::Fungsional->value,
        'pemberi_izin' => 'pta',
        'instansi_nama' => 'Pengadilan Tinggi Agama Samarinda',
        'aktif' => true,
    ]);

    [, $pejabat, $pengajuan] = buatPengajuanDisetujuiAtasanEksternal('CT');
    app(WorkflowService::class)->approvePejabat($pengajuan->id, $pejabat);

    // 1. Cari surat masuk SiSukma lewat endpoint proxy KA
    $this->actingAs($pejabat)
        ->getJson(route('cuti.sisukma.incoming-mails', ['q' => 'PTA']))
        ->assertOk()
        ->assertJsonStructure(['data']);

    // 2. Catat nomor registrasi di aplikasi PTA/MA
    $this->actingAs($pejabat)
        ->post(route('cuti.pengajuan.perizinan-eksternal.registrasi', $pengajuan->id), [
            'nomor_registrasi_eksternal' => 'REG-PTA-2026-088',
            'tanggal_pengajuan_eksternal' => '2026-11-27',
            'catatan_pengajuan' => 'Diajukan via SIKEP PTA',
        ])
        ->assertRedirect();

    $ext = CutiPerizinanEksternal::query()->where('pengajuan_id', $pengajuan->id)->firstOrFail();
    expect($ext->nomor_registrasi_eksternal)->toBe('REG-PTA-2026-088')
        ->and($ext->tanggal_pengajuan_eksternal?->toDateString())->toBe('2026-11-27');

    // 3. Catat keputusan akhir disetujui dengan menautkan SK Surat Masuk SiSukma
    $incomingPublicId = '01JTESTINCOMINGMAIL99999999';
    $this->actingAs($pejabat)
        ->post(route('cuti.pengajuan.perizinan-eksternal.keputusan', $pengajuan->id), [
            'keputusan' => 'disetujui',
            'sisukma_incoming_mail_public_id' => $incomingPublicId,
            'nomor_sk' => 'W17-A/120/KP5.3/XI/2026',
            'tanggal_sk' => '2026-11-28',
            'catatan_keputusan' => 'Disetujui Ketua PTA',
        ])
        ->assertRedirect();

    expect($pengajuan->fresh()->state->name())->toBe('DISETUJUI')
        ->and($ext->fresh()->keputusan)->toBe('disetujui')
        ->and($ext->fresh()->sisukma_incoming_mail_public_id)->toBe($incomingPublicId)
        ->and($gateway->incomingLinks)->toHaveCount(1)
        ->and(CutiSaldoLedger::query()
            ->where('pengajuan_id', $pengajuan->id)
            ->where('jenis_transaksi', 'debit_confirmed')
            ->exists())->toBeTrue()
        ->and(CutiEvent::query()->where('aggregate_id', $pengajuan->id)->where('event_type', 'cuti.disetujui')->exists())->toBeTrue();

    // 4. Unduh berkas SK eksternal lewat proxy terotorisasi KA
    $pemohon = $pengajuan->pegawai;
    $stream = $this->actingAs($pemohon)->get(route('cuti.pengajuan.sk-eksternal', $pengajuan->id));
    $stream->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');
    expect($stream->streamedContent())->toStartWith('%PDF-1.4');

    $lain = Pegawai::factory()->create();
    $this->actingAs($lain)->get(route('cuti.pengajuan.sk-eksternal', $pengajuan->id))
        ->assertForbidden();
});

test('keputusan ditolak instansi atasan dan pembatalan me-void pending saldo serta memicu event tepat', function () {
    $gateway = new FakePenomoranGateway;
    $this->app->instance(PenomoranSuratGateway::class, $gateway);

    CutiKewenangan::create([
        'jenis_cuti_kode' => 'CT',
        'jenis_jabatan' => JenisJabatan::Fungsional->value,
        'pemberi_izin' => 'ma',
        'instansi_nama' => 'Mahkamah Agung RI',
        'aktif' => true,
    ]);

    [, $pejabat1, $pTolak] = buatPengajuanDisetujuiAtasanEksternal('CT');
    app(WorkflowService::class)->approvePejabat($pTolak->id, $pejabat1);
    app(WorkflowService::class)->catatKeputusanEksternal(
        pengajuanId: $pTolak->id,
        aktor: $pejabat1,
        data: [
            'keputusan' => 'ditolak',
            'sisukma_incoming_mail_public_id' => '01JTESTINCOMINGTOLAK000001',
            'nomor_sk' => 'MA/TOLAK/2026/01',
            'tanggal_sk' => '2026-11-28',
            'catatan_keputusan' => 'Ditunda karena kepadatan sidang',
        ],
    );

    expect($pTolak->fresh()->state->name())->toBe('DITOLAK_INSTANSI_ATASAN')
        ->and(CutiSaldoLedger::query()
            ->where('pengajuan_id', $pTolak->id)
            ->where('jenis_transaksi', 'debit_void')
            ->exists())->toBeTrue()
        ->and(CutiEvent::query()->where('aggregate_id', $pTolak->id)->where('event_type', 'cuti.ditolak')->exists())->toBeTrue();

    // Kasus pembatalan sebelum keputusan akhir
    [$pemohon2, $pejabat2, $pBatal] = buatPengajuanDisetujuiAtasanEksternal('CT');
    app(WorkflowService::class)->approvePejabat($pBatal->id, $pejabat2);
    app(WorkflowService::class)->cancelDraft($pBatal->id, $pejabat2, 'Pemohon membatalkan permohonan');

    expect($pBatal->fresh()->state->name())->toBe('DIBATALKAN')
        ->and(CutiSaldoLedger::query()
            ->where('pengajuan_id', $pBatal->id)
            ->where('jenis_transaksi', 'debit_void')
            ->exists())->toBeTrue();
});
