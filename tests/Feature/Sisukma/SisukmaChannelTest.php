<?php

use App\Models\Cuti\CutiAlokasiTahunan;
use App\Models\Cuti\CutiPengajuan;
use App\Models\Cuti\CutiSaldoLedger;
use App\Models\Pegawai;
use App\Models\SuratDinas\SuratDinasRequest;
use App\Notifications\Channels\SisukmaChannel;
use App\Notifications\Cuti\PengajuanDisetujui;
use App\Notifications\Cuti\PengajuanDitolak;
use App\Notifications\Cuti\PengajuanMenungguApproval;
use App\Notifications\Cuti\PengajuanMenungguVerifikasi;
use App\Notifications\Cuti\SuratIzinFinalTersedia;
use App\Notifications\Cuti\SuratIzinNomorTerbit;
use App\Services\Cuti\PengajuanCutiService;
use App\Services\SuratDinas\Contracts\PenomoranSuratGateway;
use App\Services\SuratDinas\FakePenomoranGateway;
use App\Services\SuratDinas\Hooks\CutiSuratIzinHook;
use Database\Seeders\CutiJenisMasterSeeder;
use Database\Seeders\CutiPermissionSeeder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\FakePdfBuilder;

beforeEach(function () {
    $this->seed(CutiJenisMasterSeeder::class);
    $this->seed(CutiPermissionSeeder::class);
});

test('keempat notifikasi cuti lama tetap hanya memakai database saat sisukma.notifications_enabled false dan menambah SisukmaChannel saat true', function () {
    $pegawai = Pegawai::factory()->create();
    $pengajuan = CutiPengajuan::factory()->create([
        'pegawai_nip' => $pegawai->nip,
        'jenis_cuti_kode' => 'CT',
    ]);

    config(['sisukma.notifications_enabled' => false]);
    expect((new PengajuanDisetujui($pengajuan))->via($pegawai))->toBe(['database'])
        ->and((new PengajuanDitolak($pengajuan))->via($pegawai))->toBe(['database'])
        ->and((new PengajuanMenungguApproval($pengajuan, 'atasan_langsung'))->via($pegawai))->toBe(['database'])
        ->and((new PengajuanMenungguVerifikasi($pengajuan))->via($pegawai))->toBe(['database']);

    config(['sisukma.notifications_enabled' => true]);
    expect((new PengajuanDisetujui($pengajuan))->via($pegawai))->toBe(['database', SisukmaChannel::class])
        ->and((new PengajuanDitolak($pengajuan))->via($pegawai))->toBe(['database', SisukmaChannel::class])
        ->and((new PengajuanMenungguApproval($pengajuan, 'atasan_langsung'))->via($pegawai))->toBe(['database', SisukmaChannel::class])
        ->and((new PengajuanMenungguVerifikasi($pengajuan))->via($pegawai))->toBe(['database', SisukmaChannel::class]);
});

test('SisukmaChannel mengirim notifikasi cuti bertanda tangan HMAC ke SiSukma dan menyertakan idempotency_key', function () {
    config([
        'sisukma.enabled' => true,
        'sisukma.notifications_enabled' => true,
        'sisukma.base_url' => 'https://sisukma.test',
        'sisukma.client_id' => 'kepegawaian',
        'sisukma.secret' => 'secret-notif-ka',
        'sisukma.app_url' => 'https://kepegawaian.test',
    ]);

    Http::fake([
        'https://sisukma.test/api/integrations/v1/notifications' => Http::response([
            'data' => ['duplicate' => false, 'delivered' => true],
        ], 201),
    ]);

    $pegawai = Pegawai::factory()->create();
    $pengajuan = CutiPengajuan::factory()->approved()->create([
        'pegawai_nip' => $pegawai->nip,
        'jenis_cuti_kode' => 'CT',
    ]);

    $pegawai->notify(new PengajuanDisetujui($pengajuan));

    Http::assertSent(function ($request) use ($pegawai, $pengajuan): bool {
        return $request->url() === 'https://sisukma.test/api/integrations/v1/notifications'
            && $request->hasHeader('X-Client-Id', 'kepegawaian')
            && $request->hasHeader('X-Signature')
            && $request['recipient_nip'] === $pegawai->nip
            && str_starts_with((string) $request['action_url'], 'https://kepegawaian.test/cuti/pengajuan/'.$pengajuan->id)
            && filled($request['idempotency_key']);
    });
});

test('submit pengajuan mengirim PengajuanMenungguVerifikasi ke petugas dan hook Surat Izin mengirim notifikasi nomor terbit dan final tersedia', function () {
    Notification::fake();
    Storage::fake('local');

    Pdf::swap(new class extends FakePdfBuilder
    {
        public function save(string $path): static
        {
            parent::save($path);
            if (! is_dir(dirname($path))) {
                mkdir(dirname($path), 0755, true);
            }
            file_put_contents($path, "%PDF-1.4\n%%EOF\n");

            return $this;
        }
    });

    $pemohon = Pegawai::factory()->create();
    $petugas = Pegawai::factory()->admin()->create();
    $pemohon->update(['atasan_langsung_nip' => $petugas->nip]);

    CutiAlokasiTahunan::create([
        'pegawai_nip' => $pemohon->nip,
        'jenis_cuti_kode' => 'CT',
        'tahun_hak' => 2026,
        'hak_awal' => 12,
    ]);
    CutiSaldoLedger::create([
        'pegawai_nip' => $pemohon->nip,
        'jenis_cuti_kode' => 'CT',
        'tahun_hak' => 2026,
        'jenis_transaksi' => 'kredit',
        'jumlah_hari' => 12,
        'aktor_pegawai_nip' => $pemohon->nip,
    ]);

    config([
        'sisukma.enabled' => true,
        'sisukma.notifications_enabled' => true,
    ]);

    $pengajuan = app(PengajuanCutiService::class)->submit([
        'pegawai_nip' => $pemohon->nip,
        'jenis_cuti_kode' => 'CT',
        'tanggal_mulai' => '2026-12-07',
        'tanggal_selesai' => '2026-12-09',
        'alasan' => 'Acara keluarga',
    ]);

    Notification::assertSentTo(
        $pengajuan->petugasKepegawaianCurrent,
        PengajuanMenungguVerifikasi::class,
    );

    $this->app->instance(
        PenomoranSuratGateway::class,
        new FakePenomoranGateway,
    );

    $surat = SuratDinasRequest::factory()->menungguNomor()->create([
        'suratable_type' => $pengajuan->getMorphClass(),
        'suratable_id' => $pengajuan->id,
        'nomor_surat' => '99/KPA.W17-A8/KP5.3/XI/2026',
    ]);

    $hook = app(CutiSuratIzinHook::class);
    $hook->onNumberIssued($surat);
    $hook->onLetterAccepted($surat);

    Notification::assertSentTo($pemohon, SuratIzinNomorTerbit::class);
    Notification::assertSentTo($pemohon, SuratIzinFinalTersedia::class);
});
