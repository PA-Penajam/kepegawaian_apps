<?php

declare(strict_types=1);

require_once __DIR__.'/Support/CutiE2eHelper.php';

use App\Enums\JenisKelamin;
use App\Models\Cuti\CutiEventDelivery;
use App\Models\Cuti\CutiPengajuan;
use App\Models\Cuti\CutiSaldoLedger;
use App\Models\Pegawai;
use App\Services\Cuti\EventDispatcherService;
use App\Services\Cuti\SaldoLedgerService;
use App\Services\NomorSurat\DatabaseNomorSuratService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Seeders\CutiJenisMasterSeeder;
use Database\Seeders\CutiPermissionSeeder;
use Database\Seeders\IamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\PdfBuilder;
use Tests\E2E\Cuti\Support\CutiE2eHelper;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class)
    ->beforeEach(function (): void {
        $this->withoutVite();
        $this->seed([IamSeeder::class, CutiJenisMasterSeeder::class, CutiPermissionSeeder::class]);
    })
    ->afterEach(function (): void {
        Carbon::setTestNow();
        CarbonImmutable::setTestNow();
    })
    ->group('tier2');

test('F-01 API /api/cuti/* menolak token tanpa ability cuti, mencegah IDOR pegawai lain, dan menolak NIP non-18-digit', function (): void {
    $pemohonA = CutiE2eHelper::buatPegawaiDenganPermissions(['cuti.pengajuan.view-own', 'cuti.saldo.view-own']);
    $pemohonB = CutiE2eHelper::buatPegawaiDenganPermissions(['cuti.pengajuan.view-own', 'cuti.saldo.view-own']);
    $aktor = CutiE2eHelper::buatAktorCuti();

    CutiE2eHelper::inisialisasiSaldoCt($pemohonB, 2026, 12);
    $pengajuanB = CutiE2eHelper::buatPengajuanLangsung(
        $pemohonB,
        $aktor['petugas'],
        $aktor['atasan'],
        $aktor['pejabat']
    );

    // 1. Token tanpa ability cuti (hanya sync:pegawai) ditolak 403
    Sanctum::actingAs($pemohonA, ['sync:pegawai']);
    $this->getJson('/api/cuti/pengajuan', CutiE2eHelper::buatHeaderHmacApi('GET', '/api/cuti/pengajuan'))
        ->assertForbidden();

    // 2. Pegawai A dengan ability valid tidak boleh mengakses detail/saldo/ledger milik Pegawai B (IDOR)
    Sanctum::actingAs($pemohonA, ['cuti:read', 'app:kepegawaian']);

    $indexRes = $this->getJson('/api/cuti/pengajuan', CutiE2eHelper::buatHeaderHmacApi('GET', '/api/cuti/pengajuan'));
    $indexRes->assertSuccessful();
    expect(collect($indexRes->json('data'))->pluck('id')->all())->not->toContain($pengajuanB->id);

    $showPathB = "/api/cuti/pengajuan/{$pengajuanB->id}";
    $this->getJson($showPathB, CutiE2eHelper::buatHeaderHmacApi('GET', $showPathB))
        ->assertForbidden();

    $saldoPathB = "/api/cuti/saldo/{$pemohonB->nip}";
    $this->getJson($saldoPathB, CutiE2eHelper::buatHeaderHmacApi('GET', $saldoPathB))
        ->assertForbidden();

    $ledgerPathB = "/api/cuti/saldo/{$pemohonB->nip}/ledger";
    $this->getJson($ledgerPathB, CutiE2eHelper::buatHeaderHmacApi('GET', $ledgerPathB))
        ->assertForbidden();

    // 3. Format NIP bukan 18 digit ditolak (404 / 422)
    $invalidNipPath = '/api/cuti/saldo/12345';
    $invalidRes = $this->getJson($invalidNipPath, CutiE2eHelper::buatHeaderHmacApi('GET', $invalidNipPath));
    expect(in_array($invalidRes->status(), [404, 422], true))->toBeTrue();
});

test('F-02 pengajuan jenis cuti wajib lampiran tanpa file ditolak validasi 422', function (string $kodeJenis): void {
    $this->travelTo(CarbonImmutable::parse('2026-04-01 08:00:00'));

    $aktor = CutiE2eHelper::buatAktorCuti([
        'tmt_cpns' => '2019-01-01',
    ]);

    $tanggalSelesai = $kodeJenis === 'CS_TIER2' ? '2026-04-20' : '2026-04-08';

    $response = $this->actingAs($aktor['pemohon'])
        ->from(route('cuti.pengajuan.create'))
        ->post(route('cuti.pengajuan.store'), [
            'jenis_cuti_kode' => $kodeJenis,
            'tanggal_mulai' => '2026-04-06',
            'tanggal_selesai' => $tanggalSelesai,
            'alasan' => 'Pengajuan cuti tanpa melampirkan berkas persyaratan',
        ]);

    $response->assertSessionHasErrors('lampiran');
    expect(CutiPengajuan::where('pegawai_nip', $aktor['pemohon']->nip)->count())->toBe(0);
})->with([
    'CS_TIER2' => 'CS_TIER2',
    'CAP' => 'CAP',
    'CLTN' => 'CLTN',
]);

test('F-02 F-51 pelanggaran aturan domain mengembalikan pesan error domain jelas dan bukan error 500 generik', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-02-02 09:00:00'));

    $aktor = CutiE2eHelper::buatAktorCuti([
        'tmt_cpns' => '2018-01-01',
    ]);

    // 1. CB durasi != 90 hari kerja harus menampilkan pesan domain spesifik
    $resCb = $this->actingAs($aktor['pemohon'])
        ->from(route('cuti.pengajuan.create'))
        ->post(route('cuti.pengajuan.store'), [
            'jenis_cuti_kode' => 'CB',
            'tanggal_mulai' => '2026-02-09',
            'tanggal_selesai' => '2026-02-13',
            'alasan' => 'Mencoba cuti besar hanya 5 hari kerja',
        ]);

    $resCb->assertRedirect(route('cuti.pengajuan.create'));
    $errorCb = (string) session('error');
    expect($errorCb)->toContain('90')
        ->and($errorCb)->not->toContain('Terjadi kesalahan saat menyimpan pengajuan cuti');

    // 2. CS_TIER1 > 14 hari kalender mengembalikan pesan domain yang terinterpolasi benar
    $resCs = $this->actingAs($aktor['pemohon'])
        ->from(route('cuti.pengajuan.create'))
        ->post(route('cuti.pengajuan.store'), [
            'jenis_cuti_kode' => 'CS_TIER1',
            'tanggal_mulai' => '2026-02-10',
            'tanggal_selesai' => '2026-02-26',
            'alasan' => 'Sakit selama 17 hari kalender',
        ]);

    $resCs->assertRedirect(route('cuti.pengajuan.create'));
    $errorCs = (string) session('error');
    expect($errorCs)->toContain('14')
        ->and($errorCs)->not->toContain('{self::MAX_DURASI_KALENDER}')
        ->and($errorCs)->not->toContain('Terjadi kesalahan saat menyimpan pengajuan cuti');
});

test('F-03 validasi masa kerja CB dan CLTN menolak TMT CPNS kurang dari 5 tahun, null, maupun tanggal masa depan', function (): void {
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-03-02 09:00:00'));

    // 1. TMT CPNS < 5 tahun (4 tahun 11 bulan)
    $pegawaiKurangMasaKerja = CutiE2eHelper::buatPegawaiDenganPermissions(
        ['cuti.pengajuan.create', 'cuti.pengajuan.view-own'],
        ['tmt_cpns' => '2021-04-01']
    );

    $rentangCb = CutiE2eHelper::rentangHariKerja(CarbonImmutable::parse('2026-03-09'), 90);
    $resKurang = $this->actingAs($pegawaiKurangMasaKerja)
        ->from(route('cuti.pengajuan.create'))
        ->post(route('cuti.pengajuan.store'), [
            'jenis_cuti_kode' => 'CB',
            'tanggal_mulai' => $rentangCb['mulai'],
            'tanggal_selesai' => $rentangCb['selesai'],
            'alasan' => 'Mengajukan cuti besar sebelum genap 5 tahun',
        ]);

    $resKurang->assertRedirect(route('cuti.pengajuan.create'));
    expect((string) session('error'))->toContain('5 tahun')
        ->and(CutiPengajuan::where('pegawai_nip', $pegawaiKurangMasaKerja->nip)->count())->toBe(0);

    // 2. TMT CPNS null
    $pegawaiTanpaTmt = CutiE2eHelper::buatPegawaiDenganPermissions(
        ['cuti.pengajuan.create', 'cuti.pengajuan.view-own'],
        ['tmt_cpns' => null]
    );

    $resNull = $this->actingAs($pegawaiTanpaTmt)
        ->from(route('cuti.pengajuan.create'))
        ->post(route('cuti.pengajuan.store'), [
            'jenis_cuti_kode' => 'CLTN',
            'tanggal_mulai' => '2026-04-01',
            'tanggal_selesai' => '2026-04-10',
            'alasan' => 'Mengajukan CLTN tanpa TMT CPNS',
            'lampiran' => [CutiE2eHelper::buatFileLampiranPdf('surat.pdf')],
        ]);

    $resNull->assertRedirect(route('cuti.pengajuan.create'));
    expect((string) session('error'))->toContain('TMT CPNS')
        ->and(CutiPengajuan::where('pegawai_nip', $pegawaiTanpaTmt->nip)->count())->toBe(0);

    // 3. TMT CPNS di masa depan (sekarang 2026, tmt_cpns 2033) tidak boleh lolos karena diff absolut
    $pegawaiMasaDepan = CutiE2eHelper::buatPegawaiDenganPermissions(
        ['cuti.pengajuan.create', 'cuti.pengajuan.view-own'],
        ['tmt_cpns' => '2033-01-01']
    );

    $resFuture = $this->actingAs($pegawaiMasaDepan)
        ->from(route('cuti.pengajuan.create'))
        ->post(route('cuti.pengajuan.store'), [
            'jenis_cuti_kode' => 'CLTN',
            'tanggal_mulai' => '2026-04-01',
            'tanggal_selesai' => '2026-04-10',
            'alasan' => 'Mengajukan CLTN dengan TMT CPNS masa depan',
            'lampiran' => [CutiE2eHelper::buatFileLampiranPdf('surat-future.pdf')],
        ]);

    $resFuture->assertRedirect(route('cuti.pengajuan.create'));
    expect(session('error'))->not->toBeNull()
        ->and(CutiPengajuan::where('pegawai_nip', $pegawaiMasaDepan->nip)->count())->toBe(0);
});

test('F-15 menolak self-approval serta approver dengan *_current_nip tidak cocok melalui 403 atau redirect error, dan menentukan role reject per baris', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-05-04 09:00:00'));

    // Pemohon sekaligus punya semua permission approval tetap tidak boleh menyetujui pengajuannya sendiri
    $pemohonMultiPeran = CutiE2eHelper::buatPegawaiDenganPermissions([
        'cuti.pengajuan.create',
        'cuti.pengajuan.view-own',
        'cuti.pengajuan.verify',
        'cuti.pengajuan.approve-langsung',
        'cuti.pengajuan.approve-pejabat',
    ]);
    $aktor = CutiE2eHelper::buatAktorCuti();
    $petugasSalah = CutiE2eHelper::buatPegawaiDenganPermissions(['cuti.pengajuan.verify', 'cuti.pengajuan.view-all']);
    $atasanSalah = CutiE2eHelper::buatPegawaiDenganPermissions(['cuti.pengajuan.approve-langsung', 'cuti.pengajuan.view-team']);
    $pejabatSalah = CutiE2eHelper::buatPegawaiDenganPermissions(['cuti.pengajuan.approve-pejabat', 'cuti.pengajuan.view-team']);

    // 1. Anti-self-approval pada verify
    $pVerify = CutiE2eHelper::buatPengajuanLangsung(
        $pemohonMultiPeran,
        $pemohonMultiPeran,
        $aktor['atasan'],
        $aktor['pejabat'],
        ['state' => 'DIAJUKAN']
    );
    $resVerifySelf = $this->actingAs($pemohonMultiPeran)
        ->from(route('cuti.pengajuan.show', $pVerify->id))
        ->post(route('cuti.pengajuan.verify', $pVerify->id));

    expect($resVerifySelf->status())->not->toBe(500)
        ->and($resVerifySelf->status() === 403 || ($resVerifySelf->isRedirect() && filled(session('error'))))->toBeTrue()
        ->and($pVerify->fresh()->state->name())->toBe('DIAJUKAN');

    // 2. Wrong petugas_kepegawaian_current_nip ditolak 403 atau redirect error
    $pWrongPetugas = CutiE2eHelper::buatPengajuanLangsung(
        $aktor['pemohon'],
        $aktor['petugas'],
        $aktor['atasan'],
        $aktor['pejabat'],
        ['state' => 'DIAJUKAN']
    );
    $resWrongPetugas = $this->actingAs($petugasSalah)
        ->from(route('cuti.pengajuan.show', $pWrongPetugas->id))
        ->post(route('cuti.pengajuan.verify', $pWrongPetugas->id));

    expect($resWrongPetugas->status())->not->toBe(500)
        ->and($resWrongPetugas->status() === 403 || ($resWrongPetugas->isRedirect() && filled(session('error'))))->toBeTrue()
        ->and($pWrongPetugas->fresh()->state->name())->toBe('DIAJUKAN');

    // 3. Anti-self-approval & wrong atasan_langsung_current_nip pada approveAtasan
    $pAtasanSelf = CutiE2eHelper::buatPengajuanLangsung(
        $pemohonMultiPeran,
        $aktor['petugas'],
        $pemohonMultiPeran,
        $aktor['pejabat'],
        ['state' => 'DIVERIFIKASI']
    );
    $resAtasanSelf = $this->actingAs($pemohonMultiPeran)
        ->from(route('cuti.pengajuan.show', $pAtasanSelf->id))
        ->post(route('cuti.pengajuan.approve-atasan', $pAtasanSelf->id));

    expect($resAtasanSelf->status())->not->toBe(500)
        ->and($resAtasanSelf->status() === 403 || ($resAtasanSelf->isRedirect() && filled(session('error'))))->toBeTrue()
        ->and($pAtasanSelf->fresh()->state->name())->toBe('DIVERIFIKASI');

    $pWrongAtasan = CutiE2eHelper::buatPengajuanLangsung(
        $aktor['pemohon'],
        $aktor['petugas'],
        $aktor['atasan'],
        $aktor['pejabat'],
        ['state' => 'DIVERIFIKASI']
    );
    $resWrongAtasan = $this->actingAs($atasanSalah)
        ->from(route('cuti.pengajuan.show', $pWrongAtasan->id))
        ->post(route('cuti.pengajuan.approve-atasan', $pWrongAtasan->id));

    expect($resWrongAtasan->status())->not->toBe(500)
        ->and($resWrongAtasan->status() === 403 || ($resWrongAtasan->isRedirect() && filled(session('error'))))->toBeTrue()
        ->and($pWrongAtasan->fresh()->state->name())->toBe('DIVERIFIKASI');

    // 4. Anti-self-approval & wrong pejabat_berwenang_current_nip pada approvePejabat
    $pPejabat = CutiE2eHelper::buatPengajuanLangsung(
        $pemohonMultiPeran,
        $aktor['petugas'],
        $aktor['atasan'],
        $pemohonMultiPeran,
        ['state' => 'DISETUJUI_ATASAN']
    );
    $resPejabatSelf = $this->actingAs($pemohonMultiPeran)
        ->from(route('cuti.pengajuan.show', $pPejabat->id))
        ->post(route('cuti.pengajuan.approve-pejabat', $pPejabat->id));

    expect($resPejabatSelf->status())->not->toBe(500)
        ->and($resPejabatSelf->status() === 403 || ($resPejabatSelf->isRedirect() && filled(session('error'))))->toBeTrue()
        ->and($pPejabat->fresh()->state->name())->toBe('DISETUJUI_ATASAN');

    $pWrongPejabat = CutiE2eHelper::buatPengajuanLangsung(
        $aktor['pemohon'],
        $aktor['petugas'],
        $aktor['atasan'],
        $aktor['pejabat'],
        ['state' => 'DISETUJUI_ATASAN']
    );
    $resWrongPejabat = $this->actingAs($pejabatSalah)
        ->from(route('cuti.pengajuan.show', $pWrongPejabat->id))
        ->post(route('cuti.pengajuan.approve-pejabat', $pWrongPejabat->id));

    expect($resWrongPejabat->status())->not->toBe(500)
        ->and($resWrongPejabat->status() === 403 || ($resWrongPejabat->isRedirect() && filled(session('error'))))->toBeTrue()
        ->and($pWrongPejabat->fresh()->state->name())->toBe('DISETUJUI_ATASAN');

    // 5. Aktor ganda (verify + approve-pejabat) menolak pengajuan DIAJUKAN sebagai petugas kepegawaian
    $aktorGanda = CutiE2eHelper::buatPegawaiDenganPermissions([
        'cuti.pengajuan.verify',
        'cuti.pengajuan.approve-pejabat',
    ]);
    $pReject = CutiE2eHelper::buatPengajuanLangsung(
        $aktor['pemohon'],
        $aktorGanda,
        $aktor['atasan'],
        $aktor['pejabat'],
        ['state' => 'DIAJUKAN']
    );
    $this->actingAs($aktorGanda)
        ->post(route('cuti.pengajuan.reject', $pReject->id), [
            'alasan' => 'Dokumen pendukung kurang lengkap untuk diverifikasi',
        ])
        ->assertRedirect(route('cuti.pengajuan.show', $pReject->id));

    expect($pReject->fresh()->state->name())->toBe('DITOLAK_KEPEGAWAIAN');
});

test('F-15 route cuti.inbox multi-role menampilkan seluruh antrian lintas peran dan inbox.tsx menentukan role aksi per baris', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-05-11 09:00:00'));

    $aktor = CutiE2eHelper::buatAktorCuti();
    $approverMultiPeran = CutiE2eHelper::buatPegawaiDenganPermissions([
        'cuti.pengajuan.verify',
        'cuti.pengajuan.approve-langsung',
        'cuti.pengajuan.approve-pejabat',
    ]);

    $pemohon1 = CutiE2eHelper::buatPegawaiDenganPermissions(['cuti.pengajuan.create', 'cuti.pengajuan.view-own']);
    $pemohon2 = CutiE2eHelper::buatPegawaiDenganPermissions(['cuti.pengajuan.create', 'cuti.pengajuan.view-own']);
    $pemohon3 = CutiE2eHelper::buatPegawaiDenganPermissions(['cuti.pengajuan.create', 'cuti.pengajuan.view-own']);

    $pDiajukan = CutiE2eHelper::buatPengajuanLangsung(
        $pemohon1,
        $approverMultiPeran,
        $aktor['atasan'],
        $aktor['pejabat'],
        ['state' => 'DIAJUKAN']
    );
    $pDiverifikasi = CutiE2eHelper::buatPengajuanLangsung(
        $pemohon2,
        $aktor['petugas'],
        $approverMultiPeran,
        $aktor['pejabat'],
        ['state' => 'DIVERIFIKASI']
    );
    $pDisetujuiAtasan = CutiE2eHelper::buatPengajuanLangsung(
        $pemohon3,
        $aktor['petugas'],
        $aktor['atasan'],
        $approverMultiPeran,
        ['state' => 'DISETUJUI_ATASAN']
    );
    $pBukanMiliknya = CutiE2eHelper::buatPengajuanLangsung(
        $aktor['pemohon'],
        $aktor['petugas'],
        $aktor['atasan'],
        $aktor['pejabat'],
        ['state' => 'DIAJUKAN']
    );

    $this->actingAs($approverMultiPeran)
        ->get(route('cuti.inbox'))
        ->assertSuccessful()
        ->assertInertia(function (Assert $page) use ($pDiajukan, $pDiverifikasi, $pDisetujuiAtasan, $pBukanMiliknya): void {
            $page->component('cuti/approval/inbox')
                ->has('pengajuanList.data', 3);

            $ids = collect($page->toArray()['props']['pengajuanList']['data'])->pluck('id')->all();
            expect($ids)->toContain($pDiajukan->id)
                ->toContain($pDiverifikasi->id)
                ->toContain($pDisetujuiAtasan->id)
                ->not->toContain($pBukanMiliknya->id);
        });

    // Pastikan komponen frontend menentukan role aksi per baris, bukan resolveRole(permissions) global tunggal
    $inboxTsx = File::get(resource_path('js/pages/cuti/approval/inbox.tsx'));
    expect($inboxTsx)->not->toMatch('/const\s+role\s*=\s*resolveRole\s*\(/')
        ->and(preg_match('/DIAJUKAN|DIVERIFIKASI|DISETUJUI_ATASAN|current_nip/', $inboxTsx))->toBe(1);
});

test('F-15 menolak pencabutan cuti masa lalu dan menyimpan alasan cancel pada cuti yang dicabut', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-06-15 09:00:00'));

    $aktor = CutiE2eHelper::buatAktorCuti();
    CutiE2eHelper::inisialisasiSaldoCt($aktor['pemohon'], 2026, 12);

    // 1. Cuti masa lalu (tanggal_selesai < today) tidak boleh dicabut
    $cutiMasaLalu = CutiE2eHelper::buatPengajuanLangsung(
        $aktor['pemohon'],
        $aktor['petugas'],
        $aktor['atasan'],
        $aktor['pejabat'],
        [
            'state' => 'DISETUJUI',
            'tanggal_mulai' => '2026-06-01',
            'tanggal_selesai' => '2026-06-05',
            'jumlah_hari_kerja' => 5,
            'approved_at' => '2026-05-25 10:00:00',
        ]
    );

    $resMasaLalu = $this->actingAs($aktor['pemohon'])
        ->from(route('cuti.pengajuan.show', $cutiMasaLalu->id))
        ->post(route('cuti.pengajuan.cancel', $cutiMasaLalu->id), [
            'alasan' => 'Mencoba mencabut cuti yang sudah selesai dilaksanakan',
        ]);

    expect($resMasaLalu->status())->not->toBe(500);
    $resMasaLalu->assertRedirect(route('cuti.pengajuan.show', $cutiMasaLalu->id));
    expect((string) session('error'))->not->toBeEmpty()
        ->and($cutiMasaLalu->fresh()->state->name())->toBe('DISETUJUI')
        ->and(CutiSaldoLedger::where('pengajuan_id', $cutiMasaLalu->id)->where('jenis_transaksi', 'kredit_refund')->count())->toBe(0);

    // 2. Cuti masa depan dicabut dan alasan pembatalan wajib tersimpan
    $cutiMasaDepan = CutiE2eHelper::buatPengajuanLangsung(
        $aktor['pemohon'],
        $aktor['petugas'],
        $aktor['atasan'],
        $aktor['pejabat'],
        [
            'state' => 'DISETUJUI',
            'tanggal_mulai' => '2026-07-06',
            'tanggal_selesai' => '2026-07-08',
            'jumlah_hari_kerja' => 3,
            'approved_at' => '2026-06-10 10:00:00',
        ]
    );
    CutiSaldoLedger::create([
        'pegawai_nip' => $aktor['pemohon']->nip,
        'jenis_cuti_kode' => 'CT',
        'tahun_hak' => 2026,
        'jenis_transaksi' => 'debit_confirmed',
        'jumlah_hari' => -3,
        'pengajuan_id' => $cutiMasaDepan->id,
        'aktor_pegawai_nip' => $aktor['pemohon']->nip,
    ]);

    $alasanCancel = 'Agenda dinas mendadak sehingga cuti dibatalkan';
    $this->actingAs($aktor['pemohon'])
        ->post(route('cuti.pengajuan.cancel', $cutiMasaDepan->id), [
            'alasan' => $alasanCancel,
        ])
        ->assertRedirect(route('cuti.pengajuan.show', $cutiMasaDepan->id));

    expect($cutiMasaDepan->fresh()->state->name())->toBe('DICABUT_SETELAH_DISETUJUI');
    $this->assertDatabaseHas('cuti_pengajuan_state_history', [
        'pengajuan_id' => $cutiMasaDepan->id,
        'state_to' => 'DICABUT_SETELAH_DISETUJUI',
        'catatan' => $alasanCancel,
    ]);
});

test('F-16 saldo carry-over N-2 benar-benar hangus meskipun bucket tersebut pernah kena cap 6 hari pada tahun N-1', function (): void {
    $pegawai = Pegawai::factory()->create();
    $ledger = app(SaldoLedgerService::class);

    // Tahun 2024: alokasi 12 hari, dipakai 2 hari (sisa 10 hari)
    CutiE2eHelper::inisialisasiSaldoCt($pegawai, 2024, 12, 'Alokasi 2024');
    CutiSaldoLedger::create([
        'pegawai_nip' => $pegawai->nip,
        'jenis_cuti_kode' => 'CT',
        'tahun_hak' => 2024,
        'jenis_transaksi' => 'debit_confirmed',
        'jumlah_hari' => -2,
        'aktor_pegawai_nip' => $pegawai->nip,
        'keterangan' => 'Pemakaian 2024',
    ]);

    // Awal 2025: carry-over men-cap bucket 2024 dari 10 -> 6 hari (menulis expire cap -4)
    $this->travelTo(CarbonImmutable::parse('2025-01-02 00:05:00'));
    $this->artisan('cuti:carry-over', ['--nip' => $pegawai->nip])->assertSuccessful();
    expect($ledger->saldoBucket($pegawai->nip, 'CT', 2024))->toBe(6);

    // Awal 2026: bucket 2024 (kini N-2) harus hangus penuh menjadi 0 meski ada baris expire cap tahun lalu
    $this->travelTo(CarbonImmutable::parse('2026-01-02 00:05:00'));
    $this->artisan('cuti:carry-over', ['--nip' => $pegawai->nip])->assertSuccessful();

    expect($ledger->saldoBucket($pegawai->nip, 'CT', 2024))->toBe(0)
        ->and($ledger->saldoBucket($pegawai->nip, 'CT', 2025))->toBe(6)
        ->and($ledger->saldoBucket($pegawai->nip, 'CT', 2026))->toBe(12);
});

test('F-17 penegakan permission spesifik pada route admin cuti, cuti/saya, dan sanitasi nama file PDF tanpa slash', function (): void {
    Pdf::fake();

    $aktor = CutiE2eHelper::buatAktorCuti();
    $userTanpaCuti = CutiE2eHelper::buatPegawaiDenganPermissions([]);
    $hanyaViewAll = CutiE2eHelper::buatPegawaiDenganPermissions(['cuti.saldo.view-all']);

    // 1. User tanpa permission cuti ditolak 403 di /cuti/saya dan /cuti/pengajuan/baru
    $this->actingAs($userTanpaCuti)->get(route('cuti.saya'))->assertForbidden();
    $this->actingAs($userTanpaCuti)->get(route('cuti.pengajuan.create'))->assertForbidden();

    // 2. User dengan cuti.saldo.view-all saja (tanpa adjust/audit) ditolak 403 di /saldo/init dan /audit
    $this->actingAs($hanyaViewAll)->get(route('admin.cuti.saldo.index'))->assertSuccessful();
    $this->actingAs($hanyaViewAll)->get(route('admin.cuti.saldo.init'))->assertForbidden();
    $this->actingAs($hanyaViewAll)->post(route('admin.cuti.saldo.init.store'), [
        'pegawai_nip' => $aktor['pemohon']->nip,
        'jenis_cuti_kode' => 'CT',
        'tahun' => 2026,
        'jumlah_hari' => 12,
    ])->assertForbidden();
    $this->actingAs($hanyaViewAll)->get(route('admin.cuti.audit.index'))->assertForbidden();

    // 3. Nama file download PDF tidak boleh mengandung karakter '/' dari nomor_pengajuan
    $pengajuan = CutiE2eHelper::buatPengajuanLangsung(
        $aktor['pemohon'],
        $aktor['petugas'],
        $aktor['atasan'],
        $aktor['pejabat'],
        ['nomor_pengajuan' => 'CUTI/2026/12345678/0001']
    );

    $this->actingAs($aktor['pemohon'])
        ->get(route('cuti.pengajuan.pdf', $pengajuan->id))
        ->assertSuccessful();

    Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf): bool {
        return ! str_contains($pdf->downloadName, '/')
            && str_ends_with($pdf->downloadName, '.pdf');
    });

    // 4. Pegawai pihak ketiga tanpa hak akses dan pengguna tanpa permission cuti ditolak 403 saat unduh PDF
    $pihakKetiga = CutiE2eHelper::buatPegawaiDenganPermissions(['cuti.pengajuan.view-own']);
    $this->actingAs($pihakKetiga)
        ->get(route('cuti.pengajuan.pdf', $pengajuan->id))
        ->assertForbidden();
    $this->actingAs($userTanpaCuti)
        ->get(route('cuti.pengajuan.pdf', $pengajuan->id))
        ->assertForbidden();
});

test('F-19 DatabaseNomorSuratService menolak confirm atau release pada status non-reserved dan bulan di luar 1..12', function (): void {
    $service = app(DatabaseNomorSuratService::class);

    expect(fn () => $service->generate('KP.01', 0, 2026))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->generate('KP.01', 13, 2026))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->reserve('KP.01', 0, 2026))->toThrow(InvalidArgumentException::class)
        ->and(fn () => $service->reserve('KP.01', 13, 2026))->toThrow(InvalidArgumentException::class);

    $resConfirmed = $service->reserve('CUTI.01', 5, 2026);
    $service->confirm($resConfirmed->id);

    expect(fn () => $service->confirm($resConfirmed->id))->toThrow(Exception::class)
        ->and(fn () => $service->release($resConfirmed->id))->toThrow(Exception::class);

    $resReleased = $service->reserve('CUTI.01', 5, 2026);
    $service->release($resReleased->id);

    expect(fn () => $service->confirm($resReleased->id))->toThrow(Exception::class)
        ->and(fn () => $service->release($resReleased->id))->toThrow(Exception::class);
});

test('F-51 menolak hari kerja 0, inisialisasi/penyesuaian jenis non-saldo_driven, penyesuaian saldo negatif, serta mengizinkan reassign petugas kepegawaian', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-05-04 09:00:00'));

    $aktor = CutiE2eHelper::buatAktorCuti();
    CutiE2eHelper::inisialisasiSaldoCt($aktor['pemohon'], 2026, 5);
    $adminSaldo = CutiE2eHelper::buatPegawaiDenganPermissions(['cuti.saldo.view-all', 'cuti.saldo.adjust']);
    $adminReassign = CutiE2eHelper::buatPegawaiDenganPermissions(['cuti.pengajuan.reassign']);
    $petugasPengganti = CutiE2eHelper::buatPegawaiDenganPermissions(['cuti.pengajuan.verify']);

    // 1. Pengajuan pada akhir pekan saja (Sabtu-Minggu -> jumlah_hari_kerja = 0) ditolak
    $resWeekend = $this->actingAs($aktor['pemohon'])
        ->from(route('cuti.pengajuan.create'))
        ->post(route('cuti.pengajuan.store'), [
            'jenis_cuti_kode' => 'CT',
            'tanggal_mulai' => '2026-05-09',
            'tanggal_selesai' => '2026-05-10',
            'alasan' => 'Mencoba mengajukan cuti di hari libur akhir pekan',
        ]);
    expect($resWeekend->status())->not->toBe(500);
    expect(CutiPengajuan::where('pegawai_nip', $aktor['pemohon']->nip)->count())->toBe(0);

    // 2. Init & Adjust untuk jenis cuti non-saldo_driven (CB) ditolak validasi 422
    $this->actingAs($adminSaldo)
        ->post(route('admin.cuti.saldo.init.store'), [
            'pegawai_nip' => $aktor['pemohon']->nip,
            'jenis_cuti_kode' => 'CB',
            'tahun' => 2026,
            'jumlah_hari' => 10,
        ])
        ->assertSessionHasErrors('jenis_cuti_kode');

    $this->actingAs($adminSaldo)
        ->post(route('admin.cuti.saldo.adjust'), [
            'pegawai_nip' => $aktor['pemohon']->nip,
            'jenis_cuti_kode' => 'CB',
            'tahun' => 2026,
            'jumlah_hari' => 2,
            'keterangan' => 'Penyesuaian tidak sah pada jenis cuti besar',
        ])
        ->assertSessionHasErrors('jenis_cuti_kode');

    // 3. Penyesuaian jumlah_hari = 0 atau membuat saldo negatif (< 0) ditolak
    $this->actingAs($adminSaldo)
        ->post(route('admin.cuti.saldo.adjust'), [
            'pegawai_nip' => $aktor['pemohon']->nip,
            'jenis_cuti_kode' => 'CT',
            'tahun' => 2026,
            'jumlah_hari' => 0,
            'keterangan' => 'Penyesuaian nol hari seharusnya ditolak',
        ])
        ->assertSessionHasErrors('jumlah_hari');

    $this->actingAs($adminSaldo)
        ->post(route('admin.cuti.saldo.adjust'), [
            'pegawai_nip' => $aktor['pemohon']->nip,
            'jenis_cuti_kode' => 'CT',
            'tahun' => 2026,
            'jumlah_hari' => -10,
            'keterangan' => 'Mengurangi saldo sampai melewati batas negatif',
        ])
        ->assertSessionHasErrors();

    expect(app(SaldoLedgerService::class)->saldoBucket($aktor['pemohon']->nip, 'CT', 2026))->toBe(5);

    // 4. reassignApprover mendukung role petugas_kepegawaian
    $pengajuan = CutiE2eHelper::buatPengajuanLangsung(
        $aktor['pemohon'],
        $aktor['petugas'],
        $aktor['atasan'],
        $aktor['pejabat'],
        ['state' => 'DIAJUKAN']
    );

    $this->actingAs($adminReassign)
        ->post(route('cuti.pengajuan.reassign', $pengajuan->id), [
            'role' => 'petugas_kepegawaian',
            'new_nip' => $petugasPengganti->nip,
            'alasan' => 'Petugas kepegawaian utama sedang dinas luar kota',
        ])
        ->assertRedirect(route('cuti.pengajuan.show', $pengajuan->id));

    expect($pengajuan->fresh()->petugas_kepegawaian_current_nip)->toBe($petugasPengganti->nip);
});

test('F-50 worker cuti:dispatch-events menolak SSRF IP privat/non-HTTPS/redirect dan menangani secret korup per item tanpa menghentikan batch', function (): void {
    config([
        'cuti.consumers' => [
            'consumer-ssrf-private' => [
                'webhook_url' => 'http://127.0.0.1/internal-hook',
                'shared_secret_encrypted' => Crypt::encryptString('secret-ssrf'),
            ],
            'consumer-ssrf-https-loopback' => [
                'webhook_url' => 'https://127.0.0.1/internal-hook',
                'shared_secret_encrypted' => Crypt::encryptString('secret-ssrf-https'),
            ],
            'consumer-ssrf-metadata' => [
                'webhook_url' => 'https://169.254.169.254/latest/meta-data/',
                'shared_secret_encrypted' => Crypt::encryptString('secret-ssrf-meta'),
            ],
            'consumer-ssrf-redirect' => [
                'webhook_url' => 'https://redirector.example.test/hook',
                'shared_secret_encrypted' => Crypt::encryptString('secret-ssrf-redirect'),
            ],
            'consumer-corrupt-secret' => [
                'webhook_url' => 'https://corrupt.example.test/hook',
                'shared_secret_encrypted' => 'bukan-ciphertext-laravel-yang-valid',
            ],
            'consumer-valid' => [
                'webhook_url' => 'https://valid.example.test/hook',
                'shared_secret_encrypted' => Crypt::encryptString('secret-valid'),
            ],
        ],
    ]);

    Http::fake([
        'https://redirector.example.test/hook' => Http::response('', 302, ['Location' => 'http://127.0.0.1/secret']),
        'https://valid.example.test/hook' => Http::response(['received' => true], 200),
    ]);

    $aktor = CutiE2eHelper::buatAktorCuti();
    $pengajuan = CutiE2eHelper::buatPengajuanLangsung(
        $aktor['pemohon'],
        $aktor['petugas'],
        $aktor['atasan'],
        $aktor['pejabat'],
        ['state' => 'DISETUJUI', 'approved_at' => now()]
    );

    $event = app(EventDispatcherService::class)->dispatch('cuti.disetujui', $pengajuan);

    $this->artisan('cuti:dispatch-events')->assertSuccessful();

    $deliverySsrf = CutiEventDelivery::where('event_id', $event->id)
        ->where('consumer_id', 'consumer-ssrf-private')
        ->firstOrFail();
    $deliverySsrfHttpsLoopback = CutiEventDelivery::where('event_id', $event->id)
        ->where('consumer_id', 'consumer-ssrf-https-loopback')
        ->firstOrFail();
    $deliverySsrfMetadata = CutiEventDelivery::where('event_id', $event->id)
        ->where('consumer_id', 'consumer-ssrf-metadata')
        ->firstOrFail();
    $deliverySsrfRedirect = CutiEventDelivery::where('event_id', $event->id)
        ->where('consumer_id', 'consumer-ssrf-redirect')
        ->firstOrFail();
    $deliveryCorrupt = CutiEventDelivery::where('event_id', $event->id)
        ->where('consumer_id', 'consumer-corrupt-secret')
        ->firstOrFail();
    $deliveryValid = CutiEventDelivery::where('event_id', $event->id)
        ->where('consumer_id', 'consumer-valid')
        ->firstOrFail();

    expect($deliverySsrf->status)->not->toBe('delivered')
        ->and($deliverySsrfHttpsLoopback->status)->not->toBe('delivered')
        ->and($deliverySsrfMetadata->status)->not->toBe('delivered')
        ->and($deliverySsrfRedirect->status)->not->toBe('delivered')
        ->and($deliveryCorrupt->status)->not->toBe('delivered')
        ->and($deliveryValid->status)->toBe('delivered');

    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '127.0.0.1')
        || str_contains($request->url(), '169.254.169.254'));
});

test('F-69 tiga tabel mati cuti tidak ada lagi di skema database', function (): void {
    expect(Schema::hasTable('cuti_konfigurasi'))->toBeFalse()
        ->and(Schema::hasTable('cuti_pengajuan_periode'))->toBeFalse()
        ->and(Schema::hasTable('cuti_jenis_per_status_pegawai'))->toBeFalse();
});

test('F-02 F-51 batas minimal H-3 CT dan larangan CM bagi pegawai laki-laki ditegakkan', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-05-04 09:00:00'));

    $aktor = CutiE2eHelper::buatAktorCuti([
        'jenis_kelamin' => JenisKelamin::LakiLaki->value,
    ]);
    CutiE2eHelper::inisialisasiSaldoCt($aktor['pemohon'], 2026, 12);

    // 1. CT H-2 ditolak
    $this->actingAs($aktor['pemohon'])
        ->from(route('cuti.pengajuan.create'))
        ->post(route('cuti.pengajuan.store'), [
            'jenis_cuti_kode' => 'CT',
            'tanggal_mulai' => '2026-05-06',
            'tanggal_selesai' => '2026-05-08',
            'alasan' => 'Pengajuan mendadak kurang dari H-3',
        ])
        ->assertRedirect(route('cuti.pengajuan.create'));

    expect(CutiPengajuan::where('pegawai_nip', $aktor['pemohon']->nip)->count())->toBe(0);

    // 2. CM untuk laki-laki ditolak dengan pesan domain jelas
    $this->actingAs($aktor['pemohon'])
        ->from(route('cuti.pengajuan.create'))
        ->post(route('cuti.pengajuan.store'), [
            'jenis_cuti_kode' => 'CM',
            'tanggal_mulai' => '2026-05-11',
            'tanggal_selesai' => '2026-08-08',
            'alasan' => 'Mencoba cuti melahirkan untuk laki-laki',
        ])
        ->assertRedirect(route('cuti.pengajuan.create'));

    expect((string) session('error'))->toContain('perempuan')
        ->and(CutiPengajuan::where('pegawai_nip', $aktor['pemohon']->nip)->count())->toBe(0);
});
