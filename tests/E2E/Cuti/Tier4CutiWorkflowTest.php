<?php

declare(strict_types=1);

require_once __DIR__.'/Support/CutiE2eHelper.php';

use App\Enums\JenisKelamin;
use App\Models\Cuti\CutiEventDelivery;
use App\Models\Cuti\CutiPengajuan;
use App\Models\Cuti\CutiPengajuanLampiran;
use App\Models\Cuti\CutiSaldoLedger;
use App\Models\NomorSurat\NomorSuratReservation;
use App\Services\Cuti\SaldoLedgerService;
use App\Services\NomorSurat\DatabaseNomorSuratService;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Database\Seeders\CutiJenisMasterSeeder;
use Database\Seeders\CutiPermissionSeeder;
use Database\Seeders\IamSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
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
    ->group('tier4');

test('F-01 F-02 F-03 F-15 F-16 F-17 F-18 F-19 F-50 F-51 siklus penuh ASN mengajukan CT carry-over N-1 dan CB masa kerja >= 5 tahun hingga approval berjenjang, nomor surat, PDF, audit, dan outbox', function (): void {
    Storage::fake('local');
    Pdf::fake();

    $secretWebhook = 'rahasia-webhook-tier4-skenario1';
    config([
        'cuti.consumers' => [
            'attendance-qr-system' => [
                'webhook_url' => 'https://attendance.example.test/webhook',
                'shared_secret_encrypted' => Crypt::encryptString($secretWebhook),
            ],
        ],
    ]);

    Http::fake([
        'https://attendance.example.test/webhook' => Http::response(['ok' => true], 200),
    ]);

    // 1. Setup aktor organisasi dan dua pemohon (CT dengan carry-over N-1 & CB dengan masa kerja >= 5 tahun)
    $aktor = CutiE2eHelper::buatAktorCuti([
        'tmt_cpns' => '2019-01-01',
    ]);
    $pemohonCt = $aktor['pemohon'];
    $pemohonCb = CutiE2eHelper::buatPegawaiDenganPermissions(
        ['cuti.pengajuan.create', 'cuti.pengajuan.view-own', 'cuti.saldo.view-own'],
        [
            'tmt_cpns' => '2020-01-01',
            'jenis_kelamin' => JenisKelamin::Perempuan->value,
        ]
    );
    $auditor = CutiE2eHelper::buatPegawaiDenganPermissions(['cuti.saldo.view-all', 'cuti.audit.view']);

    // Tahun 2025: pemohon CT menyisakan 8 hari dari alokasi 12 hari
    CutiE2eHelper::inisialisasiSaldoCt($pemohonCt, 2025, 12, 'Alokasi 2025');
    CutiSaldoLedger::create([
        'pegawai_nip' => $pemohonCt->nip,
        'jenis_cuti_kode' => 'CT',
        'tahun_hak' => 2025,
        'jenis_transaksi' => 'debit_confirmed',
        'jumlah_hari' => -4,
        'aktor_pegawai_nip' => $pemohonCt->nip,
        'keterangan' => 'Pemakaian CT 2025',
    ]);

    // 2. Awal 2026: jalankan cuti:carry-over -> N-1 (2025) ter-cap 6 hari, N (2026) dikredit 12 hari (total 18)
    $this->travelTo(CarbonImmutable::parse('2026-01-02 00:05:00'));
    $this->artisan('cuti:carry-over', ['--nip' => $pemohonCt->nip])->assertSuccessful();

    // 3. Pemohon CT mengajukan 14 hari kerja (membutuhkan carry-over N-1 + jatah 2026)
    $this->travelTo(CarbonImmutable::parse('2026-02-02 09:00:00'));
    $rentangCt = CutiE2eHelper::rentangHariKerja(CarbonImmutable::parse('2026-02-09'), 14);

    $this->actingAs($pemohonCt)->post(route('cuti.pengajuan.store'), [
        'jenis_cuti_kode' => 'CT',
        'tanggal_mulai' => $rentangCt['mulai'],
        'tanggal_selesai' => $rentangCt['selesai'],
        'alasan' => 'Cuti tahunan panjang keluarga dengan carry-over',
    ])->assertRedirect();

    $pengajuanCt = CutiPengajuan::where('pegawai_nip', $pemohonCt->nip)
        ->where('jenis_cuti_kode', 'CT')
        ->firstOrFail();

    // 4. Pemohon CB (masa kerja >= 5 tahun) mengajukan Cuti Besar 90 hari kerja beserta lampiran
    $rentangCb = CutiE2eHelper::rentangHariKerja(CarbonImmutable::parse('2026-02-09'), 90);

    $this->actingAs($pemohonCb)->post(route('cuti.pengajuan.store'), [
        'jenis_cuti_kode' => 'CB',
        'tanggal_mulai' => $rentangCb['mulai'],
        'tanggal_selesai' => $rentangCb['selesai'],
        'alasan' => 'Menjalankan ibadah keagamaan dengan Cuti Besar 90 hari kerja',
        'lampiran' => [CutiE2eHelper::buatFileLampiranPdf('surat-rekomendasi-cb.pdf')],
    ])->assertRedirect();

    $pengajuanCb = CutiPengajuan::where('pegawai_nip', $pemohonCb->nip)
        ->where('jenis_cuti_kode', 'CB')
        ->firstOrFail();

    expect(CutiPengajuanLampiran::where('pengajuan_id', $pengajuanCb->id)->count())->toBe(1);

    // 5. Kedua pengajuan melewati verifikasi & approval berjenjang + penerbitan nomor surat resmi
    $nomorService = app(DatabaseNomorSuratService::class);
    foreach ([$pengajuanCt, $pengajuanCb] as $pengajuan) {
        $this->actingAs($aktor['petugas'])
            ->post(route('cuti.pengajuan.verify', $pengajuan->id), ['catatan' => 'Terverifikasi lengkap'])
            ->assertRedirect();

        $this->actingAs($aktor['atasan'])
            ->post(route('cuti.pengajuan.approve-atasan', $pengajuan->id), ['catatan' => 'Disetujui atasan'])
            ->assertRedirect();

        $reservasi = $nomorService->reserve('CUTI.01', 2, 2026);

        $this->actingAs($aktor['pejabat'])
            ->post(route('cuti.pengajuan.approve-pejabat', $pengajuan->id), ['catatan' => 'Disetujui pejabat'])
            ->assertRedirect();

        $nomorService->confirm($reservasi->id);

        expect($pengajuan->fresh()->state->name())->toBe('DISETUJUI')
            ->and(NomorSuratReservation::findOrFail($reservasi->id)->status)->toBe('confirmed');
    }

    // 6. PDF dapat diunduh oleh atasan langsung maupun admin tanpa karakter '/' di nama file
    foreach ([$aktor['atasan'], $aktor['admin']] as $pengunduh) {
        $this->actingAs($pengunduh)
            ->get(route('cuti.pengajuan.pdf', $pengajuanCt->id))
            ->assertSuccessful();

        Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf): bool {
            return $pdf->viewName === 'pdf.cuti.pengajuan'
                && ! str_contains($pdf->downloadName, '/');
        });
    }

    // 7. Audit trail cuti tercatat dengan log_name='cuti' & attribute_changes serta tampil di halaman Inertia audit
    expect(Activity::query()->where('log_name', 'cuti')->count())->toBeGreaterThan(0);

    $this->actingAs($auditor)
        ->get(route('admin.cuti.audit.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cuti/admin/audit')
            ->has('activities.data', fn (Assert $items) => $items->where('0.log_name', 'cuti')->etc())
        );

    // 8. Outbox events terkirim aman via cuti:dispatch-events
    $this->artisan('cuti:dispatch-events')->assertSuccessful();
    expect(CutiEventDelivery::where('status', 'delivered')->count())->toBeGreaterThanOrEqual(6);

    // 9. API saldo mengonfirmasi saldo CT tersisa tepat 4 hari (18 - 14)
    Sanctum::actingAs($pemohonCt, ['cuti:read', 'app:kepegawaian']);
    $saldoPath = "/api/cuti/saldo/{$pemohonCt->nip}";
    $saldoRes = $this->getJson($saldoPath, CutiE2eHelper::buatHeaderHmacApi('GET', $saldoPath))
        ->assertSuccessful();

    $bucket2026 = collect($saldoRes->json('data'))->firstWhere('tahun_hak', 2026);
    expect($bucket2026['saldo_tersedia'])->toBe(4);
});

test('F-15 F-16 F-50 F-69 siklus pembatalan, penolakan, penolakan cabut cuti lampau, dan kadaluarsa draft dengan konsistensi ledger serta outbox', function (): void {
    $secretWebhook = 'rahasia-webhook-tier4-skenario2';
    config([
        'cuti.consumers' => [
            'attendance-qr-system' => [
                'webhook_url' => 'https://attendance.example.test/webhook',
                'shared_secret_encrypted' => Crypt::encryptString($secretWebhook),
            ],
        ],
    ]);

    Http::fake([
        'https://attendance.example.test/webhook' => Http::response(['ok' => true], 200),
    ]);

    $this->travelTo(CarbonImmutable::parse('2026-06-01 09:00:00'));

    $aktor = CutiE2eHelper::buatAktorCuti();
    $ledger = app(SaldoLedgerService::class);
    CutiE2eHelper::inisialisasiSaldoCt($aktor['pemohon'], 2026, 12);

    // A. Pengajuan CT masa depan (4 hari kerja) -> disetujui penuh (saldo 8) -> dicabut dengan alasan -> saldo kembali 12
    $rentangA = CutiE2eHelper::rentangHariKerja(CarbonImmutable::parse('2026-07-06'), 4);
    $this->actingAs($aktor['pemohon'])->post(route('cuti.pengajuan.store'), [
        'jenis_cuti_kode' => 'CT',
        'tanggal_mulai' => $rentangA['mulai'],
        'tanggal_selesai' => $rentangA['selesai'],
        'alasan' => 'Rencana perjalanan dinas keluarga bulan Juli',
    ])->assertRedirect();

    $pengajuanA = CutiPengajuan::where('pegawai_nip', $aktor['pemohon']->nip)->firstOrFail();

    $this->actingAs($aktor['petugas'])->post(route('cuti.pengajuan.verify', $pengajuanA->id));
    $this->actingAs($aktor['atasan'])->post(route('cuti.pengajuan.approve-atasan', $pengajuanA->id));
    $this->actingAs($aktor['pejabat'])->post(route('cuti.pengajuan.approve-pejabat', $pengajuanA->id));

    expect($pengajuanA->fresh()->state->name())->toBe('DISETUJUI')
        ->and($ledger->saldoBucket($aktor['pemohon']->nip, 'CT', 2026))->toBe(8);

    $alasanCabut = 'Penugasan mendadak di kantor sehingga cuti dicabut';
    $this->actingAs($aktor['pemohon'])
        ->post(route('cuti.pengajuan.cancel', $pengajuanA->id), ['alasan' => $alasanCabut])
        ->assertRedirect(route('cuti.pengajuan.show', $pengajuanA->id));

    expect($pengajuanA->fresh()->state->name())->toBe('DICABUT_SETELAH_DISETUJUI')
        ->and($ledger->saldoBucket($aktor['pemohon']->nip, 'CT', 2026))->toBe(12);

    $this->assertDatabaseHas('cuti_pengajuan_state_history', [
        'pengajuan_id' => $pengajuanA->id,
        'state_to' => 'DICABUT_SETELAH_DISETUJUI',
        'catatan' => $alasanCabut,
    ]);

    // B. Pengajuan CT (3 hari kerja) -> diverifikasi -> ditolak atasan -> debit_void mengembalikan saldo tetap 12
    $rentangB = CutiE2eHelper::rentangHariKerja(CarbonImmutable::parse('2026-08-03'), 3);
    $this->actingAs($aktor['pemohon'])->post(route('cuti.pengajuan.store'), [
        'jenis_cuti_kode' => 'CT',
        'tanggal_mulai' => $rentangB['mulai'],
        'tanggal_selesai' => $rentangB['selesai'],
        'alasan' => 'Cuti tahunan bulan Agustus',
    ])->assertRedirect();

    $pengajuanB = CutiPengajuan::where('pegawai_nip', $aktor['pemohon']->nip)
        ->where('id', '!=', $pengajuanA->id)
        ->firstOrFail();

    $this->actingAs($aktor['petugas'])->post(route('cuti.pengajuan.verify', $pengajuanB->id));
    $this->actingAs($aktor['atasan'])->post(route('cuti.pengajuan.reject', $pengajuanB->id), [
        'alasan' => 'Jadwal persidangan padat pada minggu tersebut',
    ])->assertRedirect();

    expect($pengajuanB->fresh()->state->name())->toBe('DITOLAK_ATASAN')
        ->and($ledger->saldoBucket($aktor['pemohon']->nip, 'CT', 2026))->toBe(12);

    // C. Cuti masa lalu yang sudah selesai tidak dapat dicabut
    $pengajuanLampau = CutiE2eHelper::buatPengajuanLangsung(
        $aktor['pemohon'],
        $aktor['petugas'],
        $aktor['atasan'],
        $aktor['pejabat'],
        [
            'state' => 'DISETUJUI',
            'tanggal_mulai' => '2026-05-04',
            'tanggal_selesai' => '2026-05-05',
            'jumlah_hari_kerja' => 2,
            'approved_at' => '2026-04-28 10:00:00',
        ]
    );

    $resLampau = $this->actingAs($aktor['pemohon'])
        ->from(route('cuti.pengajuan.show', $pengajuanLampau->id))
        ->post(route('cuti.pengajuan.cancel', $pengajuanLampau->id), [
            'alasan' => 'Mencoba membatalkan cuti bulan lalu yang sudah selesai',
        ]);

    expect($resLampau->status())->not->toBe(500);
    $resLampau->assertRedirect(route('cuti.pengajuan.show', $pengajuanLampau->id));
    expect((string) session('error'))->not->toBeEmpty()
        ->and($pengajuanLampau->fresh()->state->name())->toBe('DISETUJUI')
        ->and(CutiSaldoLedger::where('pengajuan_id', $pengajuanLampau->id)->where('jenis_transaksi', 'kredit_refund')->count())->toBe(0);

    // D. Draft lama berusia 10 hari dikadaluarsakan oleh cuti:expire-drafts dan event dikirim via outbox
    $this->travelTo(CarbonImmutable::parse('2026-06-01 09:00:00'));
    $draftLama = CutiE2eHelper::buatPengajuanLangsung(
        $aktor['pemohon'],
        $aktor['petugas'],
        $aktor['atasan'],
        $aktor['pejabat'],
        ['state' => 'DRAFT']
    );

    $this->travelTo(CarbonImmutable::parse('2026-06-11 00:30:00'));
    $this->artisan('cuti:expire-drafts', ['--days' => 7])->assertSuccessful();

    expect($draftLama->fresh()->state->name())->toBe('DIBATALKAN');
    $this->assertDatabaseHas('cuti_pengajuan_state_history', [
        'pengajuan_id' => $draftLama->id,
        'state_from' => 'DRAFT',
        'state_to' => 'DIBATALKAN',
    ]);

    $this->artisan('cuti:dispatch-events')->assertSuccessful();
    expect(CutiEventDelivery::where('status', 'delivered')->count())->toBeGreaterThanOrEqual(3);
});
