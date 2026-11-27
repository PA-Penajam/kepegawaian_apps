<?php

use App\Models\Cuti\CutiEventDelivery;
use App\Models\Cuti\CutiPengajuan;
use App\Models\Pegawai;
use App\Services\Cuti\EventDispatcherService;
use Database\Seeders\CutiJenisMasterSeeder;
use Illuminate\Support\Facades\Crypt;

beforeEach(function () {
    $this->seed(CutiJenisMasterSeeder::class);
});

test('cuti:replay-events mengantrekan ulang event untuk consumer target tanpa mengubah delivery consumer lain', function () {
    config(['cuti.consumers' => [
        'attendance-qr-system' => [
            'webhook_url' => 'https://attendance.example.test/hook',
            'shared_secret_encrypted' => Crypt::encryptString('secret-att'),
        ],
        'sisukma' => [
            'webhook_url' => 'https://sisukma.example.test/api/integrations/v1/events/kepegawaian',
            'shared_secret_encrypted' => Crypt::encryptString('secret-sisukma'),
        ],
    ]]);

    $pegawai = Pegawai::factory()->create();

    // 1. Disetujui dan masih aktif (>= 2026-11-27), sudah punya event delivered untuk kedua consumer
    $aktif = CutiPengajuan::factory()->create([
        'pegawai_nip' => $pegawai->nip,
        'jenis_cuti_kode' => 'CT',
        'state' => 'DISETUJUI',
        'tanggal_mulai' => '2026-11-25',
        'tanggal_selesai' => '2026-11-30',
        'jumlah_hari_kerja' => 4,
    ]);
    $eventAktif = app(EventDispatcherService::class)->dispatch('cuti.disetujui', $aktif);
    CutiEventDelivery::where('event_id', $eventAktif->id)->update([
        'status' => 'delivered',
        'attempts' => 2,
        'delivered_at' => now(),
    ]);

    // 2. Disetujui & aktif, tetapi belum pernah punya CutiEvent sama sekali (mis. sebelum integrasi)
    $tanpaEvent = CutiPengajuan::factory()->create([
        'pegawai_nip' => $pegawai->nip,
        'jenis_cuti_kode' => 'CT',
        'state' => 'DISETUJUI',
        'tanggal_mulai' => '2026-12-01',
        'tanggal_selesai' => '2026-12-05',
        'jumlah_hari_kerja' => 5,
    ]);

    // 3. Disetujui tetapi sudah selesai sebelum cutoff -> tidak ikut di-replay
    $lampau = CutiPengajuan::factory()->create([
        'pegawai_nip' => $pegawai->nip,
        'jenis_cuti_kode' => 'CT',
        'state' => 'DISETUJUI',
        'tanggal_mulai' => '2026-10-01',
        'tanggal_selesai' => '2026-10-03',
        'jumlah_hari_kerja' => 3,
    ]);

    // 4. Masih DRAFT -> tidak ikut
    CutiPengajuan::factory()->create([
        'pegawai_nip' => $pegawai->nip,
        'jenis_cuti_kode' => 'CT',
        'state' => 'DRAFT',
        'tanggal_mulai' => '2026-12-01',
        'tanggal_selesai' => '2026-12-03',
        'jumlah_hari_kerja' => 3,
    ]);

    $this->artisan('cuti:replay-events', [
        '--consumer' => 'sisukma',
        '--state' => 'DISETUJUI',
        '--aktif-sejak' => '2026-11-27',
    ])->assertSuccessful();

    // Consumer attendance-qr-system pada eventAktif tetap delivered (tidak tersentuh)
    $attDelivery = CutiEventDelivery::where('event_id', $eventAktif->id)
        ->where('consumer_id', 'attendance-qr-system')
        ->firstOrFail();
    expect($attDelivery->status)->toBe('delivered')
        ->and($attDelivery->attempts)->toBe(2);

    // Consumer sisukma pada eventAktif direset jadi pending
    $sisukmaAktif = CutiEventDelivery::where('event_id', $eventAktif->id)
        ->where('consumer_id', 'sisukma')
        ->firstOrFail();
    expect($sisukmaAktif->status)->toBe('pending')
        ->and($sisukmaAktif->attempts)->toBe(0);

    // Pengajuan aktif yang belum punya event dibuatkan event + delivery khusus sisukma saja
    $sisukmaBaru = CutiEventDelivery::query()
        ->whereHas('event', fn ($q) => $q->where('aggregate_id', $tanpaEvent->id))
        ->get();
    expect($sisukmaBaru)->toHaveCount(1)
        ->and($sisukmaBaru->first()->consumer_id)->toBe('sisukma')
        ->and($sisukmaBaru->first()->status)->toBe('pending');

    // Pengajuan lampau tidak dibuatkan event
    $lampauCount = CutiEventDelivery::query()
        ->whereHas('event', fn ($q) => $q->where('aggregate_id', $lampau->id))
        ->count();
    expect($lampauCount)->toBe(0);
});

test('cuti:replay-events menolak consumer yang tidak terdaftar di konfigurasi', function () {
    $this->artisan('cuti:replay-events', [
        '--consumer' => 'consumer-tidak-ada',
    ])->assertFailed();
});
