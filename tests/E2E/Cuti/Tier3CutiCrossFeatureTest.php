<?php

declare(strict_types=1);

require_once __DIR__.'/Support/CutiE2eHelper.php';

use App\Models\Cuti\CutiEvent;
use App\Models\Cuti\CutiEventDelivery;
use App\Models\Cuti\CutiPengajuan;
use App\Models\Cuti\CutiSaldoLedger;
use App\Models\NomorSurat\NomorSuratReservation;
use App\Models\SyncConsumer;
use App\Services\Cuti\SaldoLedgerService;
use App\Services\Iam\SyncConsumerCredentialService;
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
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Spatie\Activitylog\Models\Activity;
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
    ->group('tier3');

test('F-18 F-19 F-73 kombinasi cuti multi-approval, NomorSurat, activity_log cuti attribute_changes, dan tampilan Inertia audit', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-04-06 09:00:00'));

    $aktor = CutiE2eHelper::buatAktorCuti();
    CutiE2eHelper::inisialisasiSaldoCt($aktor['pemohon'], 2026, 12);

    $rentang = CutiE2eHelper::rentangHariKerja(CarbonImmutable::parse('2026-04-13'), 3);

    // 1. Ajukan cuti melalui HTTP
    $this->actingAs($aktor['pemohon'])->post(route('cuti.pengajuan.store'), [
        'jenis_cuti_kode' => 'CT',
        'tanggal_mulai' => $rentang['mulai'],
        'tanggal_selesai' => $rentang['selesai'],
        'alasan' => 'Keperluan keluarga terencana bulan April',
    ])->assertRedirect();

    $pengajuan = CutiPengajuan::where('pegawai_nip', $aktor['pemohon']->nip)->firstOrFail();

    // 2. Reservasi nomor surat & jalankan multi-approval berjenjang
    $nomorService = app(DatabaseNomorSuratService::class);
    $reservasi = $nomorService->reserve('CUTI.01', 4, 2026);

    $this->actingAs($aktor['petugas'])
        ->post(route('cuti.pengajuan.verify', $pengajuan->id), ['catatan' => 'Terverifikasi'])
        ->assertRedirect();

    $this->actingAs($aktor['atasan'])
        ->post(route('cuti.pengajuan.approve-atasan', $pengajuan->id), ['catatan' => 'Disetujui atasan'])
        ->assertRedirect();

    $this->actingAs($aktor['pejabat'])
        ->post(route('cuti.pengajuan.approve-pejabat', $pengajuan->id), ['catatan' => 'Disetujui pejabat'])
        ->assertRedirect();

    $nomorService->confirm($reservasi->id);

    expect($pengajuan->fresh()->state->name())->toBe('DISETUJUI')
        ->and(NomorSuratReservation::findOrFail($reservasi->id)->status)->toBe('confirmed')
        ->and(NomorSuratReservation::findOrFail($reservasi->id)->nomor_lengkap)->toBe('W1-U1/1/CUTI.01/IV/2026');

    // 3. Audit log mencatat log_name='cuti' dengan attribute_changes (Activitylog v5)
    $logs = Activity::query()
        ->where('log_name', 'cuti')
        ->where('subject_id', $pengajuan->id)
        ->get();

    expect($logs)->not->toBeEmpty();

    $logPerubahanState = $logs->first(function (Activity $activity): bool {
        $changes = $activity->attribute_changes?->toArray() ?? [];

        return isset($changes['attributes']['state']) || isset($changes['attributes']);
    });
    expect($logPerubahanState)->not->toBeNull();

    // 4. Halaman Inertia admin/cuti/audit menampilkan data audit dan file audit.tsx memakai attribute_changes + Fragment ber-key
    $auditor = CutiE2eHelper::buatPegawaiDenganPermissions(['cuti.saldo.view-all', 'cuti.audit.view']);

    $this->actingAs($auditor)
        ->get(route('admin.cuti.audit.index'))
        ->assertSuccessful()
        ->assertInertia(fn (Assert $page) => $page
            ->component('cuti/admin/audit')
            ->has('activities.data', fn (Assert $items) => $items->where('0.log_name', 'cuti')->etc())
        );

    $auditTsx = File::get(resource_path('js/pages/cuti/admin/audit.tsx'));
    expect($auditTsx)->toContain('attribute_changes')
        ->and($auditTsx)->not->toMatch('/return\s*\(\s*<>/');
});

test('F-16 F-51 pengajuan CT memakai saldo carry-over N-1 dilanjutkan cuti:carry-over tahun berikutnya dan counter tahun_hak', function (): void {
    $aktor = CutiE2eHelper::buatAktorCuti();
    $ledger = app(SaldoLedgerService::class);

    // Tahun 2025: alokasi 12, terpakai 2 (sisa 10)
    CutiE2eHelper::inisialisasiSaldoCt($aktor['pemohon'], 2025, 12, 'Alokasi 2025');
    CutiSaldoLedger::create([
        'pegawai_nip' => $aktor['pemohon']->nip,
        'jenis_cuti_kode' => 'CT',
        'tahun_hak' => 2025,
        'jenis_transaksi' => 'debit_confirmed',
        'jumlah_hari' => -2,
        'aktor_pegawai_nip' => $aktor['pemohon']->nip,
        'keterangan' => 'Pemakaian CT 2025',
    ]);

    // Awal 2026: carry-over men-cap 2025 -> 6 hari, kredit 2026 -> 12 hari (total 18)
    $this->travelTo(CarbonImmutable::parse('2026-01-02 00:05:00'));
    $this->artisan('cuti:carry-over', ['--nip' => $aktor['pemohon']->nip])->assertSuccessful();

    // Pertengahan 2026: ajukan CT 8 hari kerja via HTTP lalu setujui penuh (FIFO: 6 dari 2025 + 2 dari 2026)
    $this->travelTo(CarbonImmutable::parse('2026-06-01 09:00:00'));
    $rentang2026 = CutiE2eHelper::rentangHariKerja(CarbonImmutable::parse('2026-06-08'), 8);

    $this->actingAs($aktor['pemohon'])->post(route('cuti.pengajuan.store'), [
        'jenis_cuti_kode' => 'CT',
        'tanggal_mulai' => $rentang2026['mulai'],
        'tanggal_selesai' => $rentang2026['selesai'],
        'alasan' => 'Cuti tahunan memakai saldo carry-over N-1 dan jatah N',
    ])->assertRedirect();

    $p2026 = CutiPengajuan::where('pegawai_nip', $aktor['pemohon']->nip)->firstOrFail();
    $this->actingAs($aktor['petugas'])->post(route('cuti.pengajuan.verify', $p2026->id));
    $this->actingAs($aktor['atasan'])->post(route('cuti.pengajuan.approve-atasan', $p2026->id));
    $this->actingAs($aktor['pejabat'])->post(route('cuti.pengajuan.approve-pejabat', $p2026->id));

    expect($ledger->saldoBucket($aktor['pemohon']->nip, 'CT', 2025))->toBe(0)
        ->and($ledger->saldoBucket($aktor['pemohon']->nip, 'CT', 2026))->toBe(10);

    // Akhir Desember 2026: siapkan alokasi 2027 dan ajukan CT pertama untuk awal Januari 2027 -> counter nomor 0001
    CutiE2eHelper::inisialisasiSaldoCt($aktor['pemohon'], 2027, 12, 'Alokasi awal 2027');
    $this->travelTo(CarbonImmutable::parse('2026-12-21 09:00:00'));
    $rentang2027Pertama = CutiE2eHelper::rentangHariKerja(CarbonImmutable::parse('2027-01-04'), 2);

    $this->actingAs($aktor['pemohon'])->post(route('cuti.pengajuan.store'), [
        'jenis_cuti_kode' => 'CT',
        'tanggal_mulai' => $rentang2027Pertama['mulai'],
        'tanggal_selesai' => $rentang2027Pertama['selesai'],
        'alasan' => 'Cuti awal tahun 2027 diajukan akhir Desember 2026',
    ])->assertRedirect();

    $p2027Pertama = CutiPengajuan::where('pegawai_nip', $aktor['pemohon']->nip)
        ->whereYear('tanggal_mulai', 2027)
        ->firstOrFail();

    expect($p2027Pertama->nomor_pengajuan)->toEndWith('/0001')
        ->and($p2027Pertama->nomor_pengajuan)->toStartWith('CUTI/2027/');

    // Awal 2027: jalankan cuti:carry-over -> sisa 2026 (10 - 2 pending = 8) di-cap menjadi 6 hari, 2027 tetap 12 hari
    $this->travelTo(CarbonImmutable::parse('2027-01-02 00:05:00'));
    $this->artisan('cuti:carry-over', ['--nip' => $aktor['pemohon']->nip])->assertSuccessful();

    expect($ledger->saldoBucket($aktor['pemohon']->nip, 'CT', 2026))->toBe(6)
        ->and($ledger->saldoBucket($aktor['pemohon']->nip, 'CT', 2027))->toBe(12);

    // Ajukan CT kedua tahun 2027 pada Januari 2027 -> counter harus lanjut ke 0002 (berbasis tahun_hak, bukan created_at)
    $this->travelTo(CarbonImmutable::parse('2027-01-11 09:00:00'));
    $rentang2027Kedua = CutiE2eHelper::rentangHariKerja(CarbonImmutable::parse('2027-01-18'), 2);

    $this->actingAs($aktor['pemohon'])->post(route('cuti.pengajuan.store'), [
        'jenis_cuti_kode' => 'CT',
        'tanggal_mulai' => $rentang2027Kedua['mulai'],
        'tanggal_selesai' => $rentang2027Kedua['selesai'],
        'alasan' => 'Cuti kedua tahun 2027 setelah pergantian tahun',
    ])->assertRedirect();

    $p2027Kedua = CutiPengajuan::where('pegawai_nip', $aktor['pemohon']->nip)
        ->where('id', '!=', $p2027Pertama->id)
        ->whereYear('tanggal_mulai', 2027)
        ->firstOrFail();

    expect($p2027Kedua->nomor_pengajuan)->toEndWith('/0002')
        ->and($p2027Kedua->nomor_pengajuan)->toStartWith('CUTI/2027/')
        ->and($ledger->saldoBucket($aktor['pemohon']->nip, 'CT', 2026))->toBe(4);
});

test('F-50 F-69 cuti:expire-drafts mencatat state history serta outbox lalu dikirim oleh cuti:dispatch-events termasuk pemulihan stale in_flight', function (): void {
    $secret = 'rahasia-cross-feature-outbox';
    config([
        'cuti.consumers' => [
            'attendance-e2e' => [
                'webhook_url' => 'https://attendance.example.test/webhook',
                'shared_secret_encrypted' => Crypt::encryptString($secret),
            ],
        ],
    ]);

    Http::fake([
        'https://attendance.example.test/webhook' => Http::response(['status' => 'ok'], 200),
    ]);

    $aktor = CutiE2eHelper::buatAktorCuti();

    // 1. Draft kadaluarsa berusia 10 hari
    $this->travelTo(CarbonImmutable::parse('2026-05-01 08:00:00'));
    $draftKadaluarsa = CutiE2eHelper::buatPengajuanLangsung(
        $aktor['pemohon'],
        $aktor['petugas'],
        $aktor['atasan'],
        $aktor['pejabat'],
        ['state' => 'DRAFT']
    );

    // 2. Jalankan cuti:expire-drafts pada hari ke-10
    $this->travelTo(CarbonImmutable::parse('2026-05-11 00:30:00'));
    $this->artisan('cuti:expire-drafts', ['--days' => 7])->assertSuccessful();

    expect($draftKadaluarsa->fresh()->state->name())->toBe('DIBATALKAN');

    $this->assertDatabaseHas('cuti_pengajuan_state_history', [
        'pengajuan_id' => $draftKadaluarsa->id,
        'state_from' => 'DRAFT',
        'state_to' => 'DIBATALKAN',
    ]);

    $event = CutiEvent::where('aggregate_id', $draftKadaluarsa->id)
        ->where('event_type', 'cuti.dibatalkan')
        ->firstOrFail();

    // Simulasikan delivery sempat macet di state in_flight melewati lease timeout (10 menit lalu)
    CutiEventDelivery::where('event_id', $event->id)->update([
        'status' => 'in_flight',
        'last_attempt_at' => now()->subMinutes(10),
    ]);

    // 3. Worker outbox memulihkan delivery stale in_flight dan mengirim webhook via Http::fake
    $this->artisan('cuti:dispatch-events')->assertSuccessful();

    $delivery = CutiEventDelivery::where('event_id', $event->id)->firstOrFail();
    expect($delivery->status)->toBe('delivered')
        ->and($delivery->delivered_at)->not->toBeNull();

    Http::assertSent(fn ($request): bool => $request->url() === 'https://attendance.example.test/webhook'
        && ($request->header('X-Event-Id')[0] ?? null) === $event->id);
});

test('F-01 F-25 isolasi ability SyncConsumer vs user token scoping pada endpoint API cuti', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-04-01 09:00:00'));

    $aktor = CutiE2eHelper::buatAktorCuti();
    CutiE2eHelper::inisialisasiSaldoCt($aktor['pemohon'], 2026, 12);
    $pengajuan = CutiE2eHelper::buatPengajuanLangsung(
        $aktor['pemohon'],
        $aktor['petugas'],
        $aktor['atasan'],
        $aktor['pejabat'],
    );

    // 1. SyncConsumer dengan token sync:pegawai + HMAC konsumen + X-Nonce ditolak 403 di API Cuti
    $consumer = SyncConsumer::factory()->create(['slug' => 'wfa-sync-cuti-check', 'is_active' => true]);
    $credentialService = app(SyncConsumerCredentialService::class);
    $consumerSecret = $credentialService->ensureHmacSecret($consumer);
    $issued = $credentialService->issueToken($consumer);

    auth()->forgetGuards();
    $syncHeaders = CutiE2eHelper::buatHeaderHmacApi(
        'GET',
        '/api/cuti/pengajuan',
        [],
        $consumerSecret,
        null,
        'nonce-sync-cuti-1'
    );

    $this->withToken($issued['plaintext'])
        ->withHeaders($syncHeaders)
        ->getJson('/api/cuti/pengajuan')
        ->assertForbidden();

    $saldoPath = "/api/cuti/saldo/{$aktor['pemohon']->nip}";
    $syncSaldoHeaders = CutiE2eHelper::buatHeaderHmacApi(
        'GET',
        $saldoPath,
        [],
        $consumerSecret,
        null,
        'nonce-sync-cuti-2'
    );

    $this->withToken($issued['plaintext'])
        ->withHeaders($syncSaldoHeaders)
        ->getJson($saldoPath)
        ->assertForbidden();

    // 2. User Pegawai dengan ability cuti:read & permission view-own/saldo.view-own berhasil mengakses miliknya
    Sanctum::actingAs($aktor['pemohon'], ['cuti:read', 'app:kepegawaian']);

    $this->getJson('/api/cuti/pengajuan', CutiE2eHelper::buatHeaderHmacApi('GET', '/api/cuti/pengajuan'))
        ->assertSuccessful()
        ->assertJsonPath('data.0.id', $pengajuan->id);

    $this->getJson($saldoPath, CutiE2eHelper::buatHeaderHmacApi('GET', $saldoPath))
        ->assertSuccessful()
        ->assertJsonPath('data.0.saldo_tersedia', 12);
});
