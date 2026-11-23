<?php

declare(strict_types=1);

require_once __DIR__.'/Support/CutiE2eHelper.php';

use App\Enums\JenisKelamin;
use App\Models\Cuti\CutiEventDelivery;
use App\Models\Cuti\CutiPengajuan;
use App\Models\Cuti\CutiPengajuanLampiran;
use App\Models\Cuti\CutiSaldoLedger;
use App\Models\NomorSurat\NomorSuratReservation;
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
use Illuminate\Support\Facades\DB;
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
    ->group('tier1');

test('F-02 F-51 pengajuan cuti tahunan CT berhasil diajukan dan mendebit saldo pending', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-03-02 09:00:00'));

    $aktor = CutiE2eHelper::buatAktorCuti();
    CutiE2eHelper::inisialisasiSaldoCt($aktor['pemohon'], 2026, 12);

    $rentang = CutiE2eHelper::rentangHariKerja(CarbonImmutable::parse('2026-03-09'), 5);

    $response = $this->actingAs($aktor['pemohon'])->post(route('cuti.pengajuan.store'), [
        'jenis_cuti_kode' => 'CT',
        'tanggal_mulai' => $rentang['mulai'],
        'tanggal_selesai' => $rentang['selesai'],
        'alasan' => 'Mengikuti acara keluarga tahunan di luar kota',
        'alamat_selama_cuti' => 'Jl. Merdeka No. 10, Balikpapan',
        'nomor_telp_selama_cuti' => '081234567890',
    ]);

    $pengajuan = CutiPengajuan::where('pegawai_nip', $aktor['pemohon']->nip)->first();

    expect($pengajuan)->not->toBeNull()
        ->and($pengajuan->state->name())->toBe('DIAJUKAN')
        ->and($pengajuan->jumlah_hari_kerja)->toBe(5)
        ->and($pengajuan->nomor_pengajuan)->toStartWith('CUTI/2026/');

    $response->assertRedirect(route('cuti.pengajuan.show', $pengajuan->id));

    $this->assertDatabaseHas('cuti_saldo_ledger', [
        'pengajuan_id' => $pengajuan->id,
        'jenis_transaksi' => 'debit_pending',
        'jumlah_hari' => -5,
    ]);
});

test('F-02 pengajuan CS_TIER2 menyimpan lampiran di dalam transaksi sebelum validasi rule', function (): void {
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-04-01 08:00:00'));

    $aktor = CutiE2eHelper::buatAktorCuti();
    $lampiran = CutiE2eHelper::buatFileLampiranPdf('surat-dokter-rawat-inap.pdf');

    $response = $this->actingAs($aktor['pemohon'])->post(route('cuti.pengajuan.store'), [
        'jenis_cuti_kode' => 'CS_TIER2',
        'tanggal_mulai' => '2026-04-06',
        'tanggal_selesai' => '2026-04-21',
        'alasan' => 'Pemulihan pasca operasi rawat inap sesuai surat dokter',
        'lampiran' => [$lampiran],
    ]);

    $pengajuan = CutiPengajuan::where('pegawai_nip', $aktor['pemohon']->nip)->first();

    expect($pengajuan)->not->toBeNull()
        ->and($pengajuan->state->name())->toBe('DIAJUKAN')
        ->and($pengajuan->jenis_cuti_kode)->toBe('CS_TIER2');

    $response->assertRedirect(route('cuti.pengajuan.show', $pengajuan->id));

    $recordLampiran = CutiPengajuanLampiran::where('pengajuan_id', $pengajuan->id)->first();
    expect($recordLampiran)->not->toBeNull()
        ->and($recordLampiran->nama_file_asli)->toBe('surat-dokter-rawat-inap.pdf');

    Storage::disk('local')->assertExists($recordLampiran->path_file);
});

test('F-02 F-03 pengajuan CAP dan CLTN dengan lampiran serta masa kerja >= 5 tahun berhasil diajukan', function (): void {
    Storage::fake('local');
    $this->travelTo(CarbonImmutable::parse('2026-05-04 08:00:00'));

    $aktor = CutiE2eHelper::buatAktorCuti([
        'tmt_cpns' => '2020-01-01',
    ]);

    // Pengajuan CAP dengan lampiran
    $resCap = $this->actingAs($aktor['pemohon'])->post(route('cuti.pengajuan.store'), [
        'jenis_cuti_kode' => 'CAP',
        'tanggal_mulai' => '2026-05-06',
        'tanggal_selesai' => '2026-05-08',
        'alasan' => 'Keluarga inti sakit keras dan membutuhkan pendampingan',
        'lampiran' => [CutiE2eHelper::buatFileLampiranPdf('surat-rs.pdf')],
    ]);

    $cap = CutiPengajuan::where('pegawai_nip', $aktor['pemohon']->nip)
        ->where('jenis_cuti_kode', 'CAP')
        ->first();

    expect($cap)->not->toBeNull()->and($cap->state->name())->toBe('DIAJUKAN');
    $resCap->assertRedirect(route('cuti.pengajuan.show', $cap->id));
    expect(CutiPengajuanLampiran::where('pengajuan_id', $cap->id)->count())->toBe(1);

    // Pengajuan CLTN dengan lampiran dan masa kerja > 5 tahun (CarbonImmutable)
    $resCltn = $this->actingAs($aktor['pemohon'])->post(route('cuti.pengajuan.store'), [
        'jenis_cuti_kode' => 'CLTN',
        'tanggal_mulai' => '2026-06-01',
        'tanggal_selesai' => '2026-06-15',
        'alasan' => 'Mendampingi pasangan tugas belajar di luar negeri',
        'lampiran' => [CutiE2eHelper::buatFileLampiranPdf('sk-tugas-pasangan.pdf')],
    ]);

    $cltn = CutiPengajuan::where('pegawai_nip', $aktor['pemohon']->nip)
        ->where('jenis_cuti_kode', 'CLTN')
        ->first();

    expect($cltn)->not->toBeNull()->and($cltn->state->name())->toBe('DIAJUKAN');
    $resCltn->assertRedirect(route('cuti.pengajuan.show', $cltn->id));
    expect(CutiPengajuanLampiran::where('pengajuan_id', $cltn->id)->count())->toBe(1);
});

test('F-02 F-03 pengajuan CB 90 hari kerja dengan CarbonImmutable >= 5 tahun dan CM perempuan berhasil diajukan', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-01-15 09:00:00'));

    // Siapkan aktor workflow agar snapshot approver terisi
    $aktor = CutiE2eHelper::buatAktorCuti([
        'jenis_kelamin' => JenisKelamin::Perempuan->value,
        'tmt_cpns' => '2021-01-01',
    ]);

    // Pegawai perempuan pertama mengajukan CB 90 hari kerja
    $rentangCb = CutiE2eHelper::rentangHariKerja(CarbonImmutable::parse('2026-02-02'), 90);

    $resCb = $this->actingAs($aktor['pemohon'])->post(route('cuti.pengajuan.store'), [
        'jenis_cuti_kode' => 'CB',
        'tanggal_mulai' => $rentangCb['mulai'],
        'tanggal_selesai' => $rentangCb['selesai'],
        'alasan' => 'Menunaikan ibadah keagamaan dengan cuti besar',
    ]);

    $cb = CutiPengajuan::where('pegawai_nip', $aktor['pemohon']->nip)
        ->where('jenis_cuti_kode', 'CB')
        ->first();

    expect($cb)->not->toBeNull()
        ->and($cb->state->name())->toBe('DIAJUKAN')
        ->and($cb->jumlah_hari_kerja)->toBe(90);
    $resCb->assertRedirect(route('cuti.pengajuan.show', $cb->id));

    // Pegawai perempuan kedua mengajukan CM
    $pemohonCm = CutiE2eHelper::buatPegawaiDenganPermissions(
        ['cuti.pengajuan.create', 'cuti.pengajuan.view-own'],
        ['jenis_kelamin' => JenisKelamin::Perempuan->value]
    );

    $resCm = $this->actingAs($pemohonCm)->post(route('cuti.pengajuan.store'), [
        'jenis_cuti_kode' => 'CM',
        'tanggal_mulai' => '2026-02-02',
        'tanggal_selesai' => '2026-05-02',
        'alasan' => 'Persiapan persalinan anak pertama sesuai HPL dokter',
    ]);

    $cm = CutiPengajuan::where('pegawai_nip', $pemohonCm->nip)
        ->where('jenis_cuti_kode', 'CM')
        ->first();

    expect($cm)->not->toBeNull()->and($cm->state->name())->toBe('DIAJUKAN');
    $resCm->assertRedirect(route('cuti.pengajuan.show', $cm->id));
});

test('F-15 F-17 alur verifikasi hingga approve pejabat dan unduh PDF oleh pemohon atasan serta admin', function (): void {
    Pdf::fake();
    $this->travelTo(CarbonImmutable::parse('2026-06-01 09:00:00'));

    $aktor = CutiE2eHelper::buatAktorCuti();
    CutiE2eHelper::inisialisasiSaldoCt($aktor['pemohon'], 2026, 12);

    $rentang = CutiE2eHelper::rentangHariKerja(CarbonImmutable::parse('2026-06-15'), 4);

    $this->actingAs($aktor['pemohon'])->post(route('cuti.pengajuan.store'), [
        'jenis_cuti_kode' => 'CT',
        'tanggal_mulai' => $rentang['mulai'],
        'tanggal_selesai' => $rentang['selesai'],
        'alasan' => 'Keperluan keluarga terencana di luar daerah',
    ])->assertRedirect();

    $pengajuan = CutiPengajuan::where('pegawai_nip', $aktor['pemohon']->nip)->firstOrFail();

    // 1. Verifikasi oleh petugas kepegawaian
    $this->actingAs($aktor['petugas'])
        ->post(route('cuti.pengajuan.verify', $pengajuan->id), ['catatan' => 'Berkas lengkap'])
        ->assertRedirect(route('cuti.pengajuan.show', $pengajuan->id));

    expect($pengajuan->fresh()->state->name())->toBe('DIVERIFIKASI');

    // 2. Persetujuan oleh atasan langsung
    $this->actingAs($aktor['atasan'])
        ->post(route('cuti.pengajuan.approve-atasan', $pengajuan->id), ['catatan' => 'Disetujui atasan'])
        ->assertRedirect(route('cuti.pengajuan.show', $pengajuan->id));

    expect($pengajuan->fresh()->state->name())->toBe('DISETUJUI_ATASAN');

    // 3. Persetujuan final oleh pejabat berwenang
    $this->actingAs($aktor['pejabat'])
        ->post(route('cuti.pengajuan.approve-pejabat', $pengajuan->id), ['catatan' => 'Disetujui pejabat'])
        ->assertRedirect(route('cuti.pengajuan.show', $pengajuan->id));

    expect($pengajuan->fresh()->state->name())->toBe('DISETUJUI')
        ->and(app(SaldoLedgerService::class)->saldoBucket($aktor['pemohon']->nip, 'CT', 2026))->toBe(8);

    // 4. Unduh PDF oleh pemohon, atasan langsung, dan admin
    foreach ([$aktor['pemohon'], $aktor['atasan'], $aktor['admin']] as $pengunduh) {
        $this->actingAs($pengunduh)
            ->get(route('cuti.pengajuan.pdf', $pengajuan->id))
            ->assertSuccessful();

        Pdf::assertRespondedWithPdf(function (PdfBuilder $pdf): bool {
            return $pdf->viewName === 'pdf.cuti.pengajuan'
                && ! str_contains($pdf->downloadName, '/');
        });
    }
});

test('F-16 carry-over tahunan cuti:carry-over dan penggunaan saldo N-1 pada CutiTahunanRule', function (): void {
    $aktor = CutiE2eHelper::buatAktorCuti();

    // Tahun 2025 memiliki sisa saldo 8 hari
    CutiE2eHelper::inisialisasiSaldoCt($aktor['pemohon'], 2025, 12, 'Alokasi 2025');
    CutiSaldoLedger::create([
        'pegawai_nip' => $aktor['pemohon']->nip,
        'jenis_cuti_kode' => 'CT',
        'tahun_hak' => 2025,
        'jenis_transaksi' => 'debit_confirmed',
        'jumlah_hari' => -4,
        'aktor_pegawai_nip' => $aktor['pemohon']->nip,
        'keterangan' => 'Pemakaian CT 2025',
    ]);

    // Masuk tahun 2026: jalankan cuti:carry-over
    $this->travelTo(CarbonImmutable::parse('2026-01-02 00:05:00'));

    $this->artisan('cuti:carry-over', ['--nip' => $aktor['pemohon']->nip])
        ->assertSuccessful();

    $ledger = app(SaldoLedgerService::class);
    expect($ledger->saldoBucket($aktor['pemohon']->nip, 'CT', 2025))->toBe(6)
        ->and($ledger->saldoBucket($aktor['pemohon']->nip, 'CT', 2026))->toBe(12);

    // Ajukan CT 15 hari kerja (melebihi jatah 2026 = 12, sehingga butuh carry-over 2025)
    $this->travelTo(CarbonImmutable::parse('2026-02-02 09:00:00'));
    $rentang = CutiE2eHelper::rentangHariKerja(CarbonImmutable::parse('2026-02-09'), 15);

    $this->actingAs($aktor['pemohon'])->post(route('cuti.pengajuan.store'), [
        'jenis_cuti_kode' => 'CT',
        'tanggal_mulai' => $rentang['mulai'],
        'tanggal_selesai' => $rentang['selesai'],
        'alasan' => 'Cuti tahunan panjang memanfaatkan sisa cuti tahun lalu',
    ])->assertRedirect();

    $pengajuan = CutiPengajuan::where('pegawai_nip', $aktor['pemohon']->nip)->firstOrFail();

    expect($pengajuan->state->name())->toBe('DIAJUKAN')
        ->and($ledger->saldoBucket($aktor['pemohon']->nip, 'CT', 2025))->toBe(0)
        ->and($ledger->saldoBucket($aktor['pemohon']->nip, 'CT', 2026))->toBe(3);
});

test('F-18 F-73 halaman admin/cuti/audit menampilkan log dengan log_name cuti dan attribute_changes', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-03-02 10:00:00'));

    $aktor = CutiE2eHelper::buatAktorCuti();
    CutiE2eHelper::inisialisasiSaldoCt($aktor['pemohon'], 2026, 12);

    $rentang = CutiE2eHelper::rentangHariKerja(CarbonImmutable::parse('2026-03-16'), 3);

    $this->actingAs($aktor['pemohon'])->post(route('cuti.pengajuan.store'), [
        'jenis_cuti_kode' => 'CT',
        'tanggal_mulai' => $rentang['mulai'],
        'tanggal_selesai' => $rentang['selesai'],
        'alasan' => 'Keperluan pribadi terjadwal',
    ])->assertRedirect();

    $pengajuan = CutiPengajuan::where('pegawai_nip', $aktor['pemohon']->nip)->firstOrFail();
    $this->actingAs($aktor['petugas'])
        ->post(route('cuti.pengajuan.verify', $pengajuan->id), ['catatan' => 'Diverifikasi']);

    // Pastikan Activitylog v5 mencatat log_name = 'cuti' dan kolom attribute_changes
    $cutiLogs = Activity::query()->where('log_name', 'cuti')->get();
    expect($cutiLogs)->not->toBeEmpty();

    $adaAttributeChanges = $cutiLogs->contains(function (Activity $activity): bool {
        $changes = $activity->attribute_changes?->toArray() ?? [];

        return $changes !== [];
    });
    expect($adaAttributeChanges)->toBeTrue();

    // Akses Inertia admin/cuti/audit oleh aktor berizin
    $auditor = CutiE2eHelper::buatPegawaiDenganPermissions(['cuti.saldo.view-all', 'cuti.audit.view']);

    $this->actingAs($auditor)
        ->get(route('admin.cuti.audit.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cuti/admin/audit')
            ->has('activities.data', fn (Assert $items) => $items->where('0.log_name', 'cuti')->etc())
        );
});

test('F-19 DatabaseNomorSuratService generate menghasilkan nomor berurutan dan reset per tahun', function (): void {
    $service = app(DatabaseNomorSuratService::class);

    $nomor1 = $service->generate('KP.01.1', 5, 2026);
    $nomor2 = $service->generate('KP.01.1', 6, 2026);
    $nomorTahunBaru = $service->generate('KP.01.1', 1, 2027);

    expect($nomor1)->toBe('W1-U1/1/KP.01.1/V/2026')
        ->and($nomor2)->toBe('W1-U1/2/KP.01.1/VI/2026')
        ->and($nomorTahunBaru)->toBe('W1-U1/1/KP.01.1/I/2027');
});

test('F-19 DatabaseNomorSuratService mendukung reserve confirm dan release dengan hole policy', function (): void {
    $service = app(DatabaseNomorSuratService::class);

    $res1 = $service->reserve('SK.01', 3, 2026);
    expect($res1->status)->toBe('reserved')
        ->and($res1->nomor_lengkap)->toBe('W1-U1/1/SK.01/III/2026');

    $service->confirm($res1->id);
    $res1Fresh = NomorSuratReservation::findOrFail($res1->id);
    expect($res1Fresh->status)->toBe('confirmed')
        ->and($res1Fresh->confirmed_at)->not->toBeNull();

    $res2 = $service->reserve('SK.01', 3, 2026);
    expect($res2->nomor_lengkap)->toBe('W1-U1/2/SK.01/III/2026');

    $service->release($res2->id);
    $res2Fresh = NomorSuratReservation::findOrFail($res2->id);
    expect($res2Fresh->status)->toBe('released')
        ->and($res2Fresh->released_at)->not->toBeNull();

    // Hole policy: reservasi berikutnya memakai urut 3, tidak mengisi nomor 2 yang di-release
    $res3 = $service->reserve('SK.01', 3, 2026);
    expect($res3->nomor_lengkap)->toBe('W1-U1/3/SK.01/III/2026');
});

test('F-50 worker cuti:dispatch-events mengirim outbox webhook HTTPS bertanda tangan HMAC', function (): void {
    $secret = 'rahasia-webhook-cuti-e2e';
    config([
        'cuti.consumers' => [
            'attendance-e2e' => [
                'webhook_url' => 'https://attendance.example.test/webhook',
                'shared_secret_encrypted' => Crypt::encryptString($secret),
            ],
        ],
    ]);

    Http::fake([
        'https://attendance.example.test/webhook' => Http::response(['ok' => true], 200),
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

    $delivery = CutiEventDelivery::where('event_id', $event->id)->firstOrFail();
    expect($delivery->status)->toBe('delivered')
        ->and($delivery->attempts)->toBe(1)
        ->and($delivery->delivered_at)->not->toBeNull();

    Http::assertSent(function ($request) use ($event, $secret): bool {
        $eventId = $request->header('X-Event-Id')[0] ?? '';
        $timestamp = $request->header('X-Timestamp')[0] ?? '';
        $signature = $request->header('X-Signature')[0] ?? '';
        $expected = hash_hmac('sha256', "{$eventId}.{$timestamp}.{$request->body()}", $secret);

        return $request->url() === 'https://attendance.example.test/webhook'
            && $eventId === $event->id
            && hash_equals($expected, $signature);
    });
});

test('F-69 command cuti:expire-drafts mengkadaluarsakan draft lama dengan state history dan outbox event', function (): void {
    config([
        'cuti.consumers' => [
            'attendance-e2e' => [
                'webhook_url' => 'https://attendance.example.test/webhook',
                'shared_secret_encrypted' => Crypt::encryptString('secret-e2e'),
            ],
        ],
    ]);

    $aktor = CutiE2eHelper::buatAktorCuti();

    $this->travelTo(CarbonImmutable::parse('2026-05-01 08:00:00'));
    $draftLama = CutiE2eHelper::buatPengajuanLangsung(
        $aktor['pemohon'],
        $aktor['petugas'],
        $aktor['atasan'],
        $aktor['pejabat'],
        ['state' => 'DRAFT']
    );

    $this->travelTo(CarbonImmutable::parse('2026-05-09 08:00:00'));
    $draftBaru = CutiE2eHelper::buatPengajuanLangsung(
        $aktor['pemohon'],
        $aktor['petugas'],
        $aktor['atasan'],
        $aktor['pejabat'],
        ['state' => 'DRAFT']
    );

    $this->travelTo(CarbonImmutable::parse('2026-05-10 00:30:00'));
    $this->artisan('cuti:expire-drafts', ['--days' => 7])->assertSuccessful();

    expect($draftLama->fresh()->state->name())->toBe('DIBATALKAN')
        ->and($draftLama->fresh()->cancelled_at)->not->toBeNull()
        ->and($draftBaru->fresh()->state->name())->toBe('DRAFT');

    $this->assertDatabaseHas('cuti_pengajuan_state_history', [
        'pengajuan_id' => $draftLama->id,
        'state_from' => 'DRAFT',
        'state_to' => 'DIBATALKAN',
    ]);

    $this->assertDatabaseHas('cuti_events', [
        'aggregate_id' => $draftLama->id,
        'event_type' => 'cuti.dibatalkan',
    ]);
});

test('F-01 API /api/cuti/pengajuan mengembalikan daftar dan detail pengajuan dengan ability dan otorisasi sah', function (): void {
    $aktor = CutiE2eHelper::buatAktorCuti();
    $pengajuan = CutiE2eHelper::buatPengajuanLangsung(
        $aktor['pemohon'],
        $aktor['petugas'],
        $aktor['atasan'],
        $aktor['pejabat'],
    );

    Sanctum::actingAs($aktor['pemohon'], ['cuti:read', 'app:kepegawaian']);

    $indexHeaders = CutiE2eHelper::buatHeaderHmacApi('GET', '/api/cuti/pengajuan');
    $this->getJson('/api/cuti/pengajuan', $indexHeaders)
        ->assertSuccessful()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $pengajuan->id);

    $showPath = "/api/cuti/pengajuan/{$pengajuan->id}";
    $showHeaders = CutiE2eHelper::buatHeaderHmacApi('GET', $showPath);
    $this->getJson($showPath, $showHeaders)
        ->assertSuccessful()
        ->assertJsonPath('data.id', $pengajuan->id)
        ->assertJsonPath('data.pegawai_nip', $aktor['pemohon']->nip);
});

test('F-01 API /api/cuti/saldo/{nip} mengembalikan ringkasan saldo dan ledger dengan ability, otorisasi sah, serta bebas query N+1', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-03-01 08:00:00'));

    $aktor = CutiE2eHelper::buatAktorCuti();
    foreach ([2022, 2023, 2024, 2025, 2026] as $tahun) {
        CutiE2eHelper::inisialisasiSaldoCt($aktor['pemohon'], $tahun, 12, "Alokasi {$tahun}");
    }

    Sanctum::actingAs($aktor['pemohon'], ['cuti:read', 'app:kepegawaian']);

    $saldoPath = "/api/cuti/saldo/{$aktor['pemohon']->nip}";

    DB::flushQueryLog();
    DB::enableQueryLog();
    try {
        $this->getJson($saldoPath, CutiE2eHelper::buatHeaderHmacApi('GET', $saldoPath))
            ->assertSuccessful()
            ->assertJsonCount(5, 'data')
            ->assertJsonPath('data.0.jenis_cuti_kode', 'CT')
            ->assertJsonPath('data.0.tahun_hak', 2026)
            ->assertJsonPath('data.0.saldo_tersedia', 12);
    } finally {
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
    }

    // Pastikan penghitungan saldo banyak bucket tahun tidak memicu pola query N+1 pada cuti_saldo_ledger
    $ledgerQueryCount = collect($queries)
        ->filter(fn (array $query): bool => str_contains(strtolower((string) ($query['query'] ?? '')), 'cuti_saldo_ledger'))
        ->count();

    expect($ledgerQueryCount)->toBeLessThanOrEqual(3);

    $ledgerPath = "/api/cuti/saldo/{$aktor['pemohon']->nip}/ledger";
    $this->getJson($ledgerPath, CutiE2eHelper::buatHeaderHmacApi('GET', $ledgerPath))
        ->assertSuccessful()
        ->assertJsonStructure(['data', 'links', 'meta']);
});

test('F-17 F-51 route /cuti/saya dan /admin/cuti/saldo/init dapat diakses dan berfungsi untuk pengguna berizin', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-04-01 09:00:00'));

    $aktor = CutiE2eHelper::buatAktorCuti();
    $adminSaldo = CutiE2eHelper::buatPegawaiDenganPermissions(['cuti.saldo.view-all', 'cuti.saldo.adjust']);

    // Halaman cuti/saya untuk pegawai berizin
    $this->actingAs($aktor['pemohon'])
        ->get(route('cuti.saya'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cuti/saldo/my-dashboard')
            ->has('saldo')
            ->has('pengajuanList')
        );

    // Halaman dan submit inisialisasi saldo oleh admin berizin
    $this->actingAs($adminSaldo)
        ->get(route('admin.cuti.saldo.init'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page->component('cuti/saldo/admin-init'));

    $this->actingAs($adminSaldo)
        ->post(route('admin.cuti.saldo.init.store'), [
            'pegawai_nip' => $aktor['pemohon']->nip,
            'jenis_cuti_kode' => 'CT',
            'tahun' => 2026,
            'jumlah_hari' => 12,
            'keterangan' => 'Inisialisasi saldo awal tahun 2026',
        ])
        ->assertRedirect(route('admin.cuti.saldo.index'));

    expect(app(SaldoLedgerService::class)->saldoBucket($aktor['pemohon']->nip, 'CT', 2026))->toBe(12);
});
