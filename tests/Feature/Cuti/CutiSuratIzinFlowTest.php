<?php

use App\Enums\StatusSuratDinas;
use App\Models\Cuti\CutiAlokasiTahunan;
use App\Models\Cuti\CutiJenisMaster;
use App\Models\Cuti\CutiPengajuan;
use App\Models\Cuti\CutiPengajuanPdf;
use App\Models\IamApplication;
use App\Models\IamPermission;
use App\Models\IamRole;
use App\Models\Pegawai;
use App\Models\SuratDinas\SuratDinasRequest;
use App\Services\Cuti\SaldoLedgerService;
use App\Services\Cuti\WorkflowService;
use App\Services\SuratDinas\Contracts\PenomoranSuratGateway;
use App\Services\SuratDinas\FakePenomoranGateway;
use Database\Seeders\CutiJenisMasterSeeder;
use Database\Seeders\CutiPermissionSeeder;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\FakePdfBuilder;

beforeEach(function () {
    $this->seed(CutiJenisMasterSeeder::class);
    $this->seed(CutiPermissionSeeder::class);
});

function kaF1SignedWebhook(string $eventType, array $data, string $secret): array
{
    $eventId = (string) Str::uuid();
    $ts = (string) now()->timestamp;
    $rawBody = json_encode([
        'event_id' => $eventId,
        'event_type' => $eventType,
        'occurred_at' => now()->toIso8601String(),
        'data' => $data,
    ], JSON_THROW_ON_ERROR);
    $signature = hash_hmac('sha256', "{$eventId}.{$ts}.{$rawBody}", $secret);

    return [
        'rawBody' => $rawBody,
        'server' => [
            'CONTENT_TYPE' => 'application/json',
            'HTTP_ACCEPT' => 'application/json',
            'HTTP_X_EVENT_ID' => $eventId,
            'HTTP_X_TIMESTAMP' => $ts,
            'HTTP_X_SIGNATURE' => $signature,
        ],
    ];
}

function kaF1SwapFakePdfDiskWriter(): void
{
    Pdf::swap(new class extends FakePdfBuilder
    {
        public function save(string $path): static
        {
            parent::save($path);

            $dir = dirname($path);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }

            file_put_contents($path, "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n");

            return $this;
        }
    });
}

test('admin dapat mengatur penandatangan surat per jenis cuti dan non-admin ditolak 403', function () {
    $admin = Pegawai::factory()->admin()->create();
    $biasa = Pegawai::factory()->create();

    $this->actingAs($biasa)->get(route('admin.cuti.jenis.index'))->assertForbidden();
    $this->actingAs($biasa)->patch(route('admin.cuti.jenis.update', 'CT'), [
        'penandatangan_surat' => 'sekretaris',
    ])->assertForbidden();

    $this->actingAs($admin)->get(route('admin.cuti.jenis.index'))->assertOk();
    $this->actingAs($admin)->patch(route('admin.cuti.jenis.update', 'CT'), [
        'penandatangan_surat' => 'sekretaris',
    ])->assertRedirect();

    expect(CutiJenisMaster::find('CT')->penandatangan_surat)->toBe('sekretaris');
});

test('approvePejabat mendaftarkan Surat Izin Cuti ke SiSukma, merender draf bernomor saat number_issued, dan menerima letter_accepted', function () {
    Storage::fake('local');
    kaF1SwapFakePdfDiskWriter();

    $gateway = new FakePenomoranGateway;
    $this->app->instance(PenomoranSuratGateway::class, $gateway);

    $secret = 'secret-webhook-f1';
    config([
        'sisukma.enabled' => true,
        'sisukma.webhook_secret' => $secret,
    ]);

    CutiJenisMaster::where('kode', 'CT')->update(['penandatangan_surat' => 'sekretaris']);

    $pegawai = Pegawai::factory()->create();
    $pejabat = Pegawai::factory()->admin()->create();
    app(SaldoLedgerService::class)->kreditAlokasi($pegawai->nip, 'CT', now()->year, 12, 'Alokasi');

    $pengajuan = CutiPengajuan::factory()->create([
        'pegawai_nip' => $pegawai->nip,
        'jenis_cuti_kode' => 'CT',
        'tanggal_mulai' => now()->addWeeks(2)->toDateString(),
        'tanggal_selesai' => now()->addWeeks(2)->addDays(2)->toDateString(),
        'jumlah_hari_kerja' => 3,
        'state' => 'DISETUJUI_ATASAN',
        'pejabat_berwenang_current_nip' => $pejabat->nip,
    ]);

    app(WorkflowService::class)->approvePejabat($pengajuan->id, $pejabat, 'Disetujui');

    $surat = SuratDinasRequest::query()
        ->where('suratable_id', $pengajuan->id)
        ->first();

    expect($surat)->not->toBeNull()
        ->and($surat->jenis)->toBe('surat_izin_cuti')
        ->and($surat->klasifikasi)->toBe('KP5.3')
        ->and($surat->penandatangan)->toBe('sekretaris')
        ->and($surat->status)->toBe(StatusSuratDinas::MenungguNomor)
        ->and($gateway->registered)->toHaveCount(1);

    // Simulasikan Admin TU meminta perbaikan di SiSukma
    $rev = kaF1SignedWebhook('outgoing_mail.revision_requested', [
        'public_id' => $surat->sisukma_public_id,
        'external_reference' => $surat->id,
        'revision_note' => 'Lengkapi unit tujuan',
    ], $secret);
    $this->call('POST', '/api/integrations/sisukma/events', [], [], [], $rev['server'], $rev['rawBody'])
        ->assertStatus(202);

    expect($surat->fresh()->status)->toBe(StatusSuratDinas::PerluPerbaikan)
        ->and($surat->fresh()->catatan_revisi)->toBe('Lengkapi unit tujuan');

    // Petugas memperbaiki dan mengajukan ulang dari KA
    $app = IamApplication::where('slug', 'kepegawaian')->firstOrFail();
    $verifyPerm = IamPermission::where('iam_application_id', $app->id)->where('slug', 'cuti.pengajuan.verify')->firstOrFail();
    $petugasRole = IamRole::create(['iam_application_id' => $app->id, 'slug' => 'petugas-cuti', 'nama' => 'Petugas Cuti']);
    $petugasRole->permissions()->attach($verifyPerm->id);
    $petugas = Pegawai::factory()->create();
    $petugas->iamRoles()->attach($petugasRole->id, ['assigned_at' => now()]);

    $this->actingAs($petugas)
        ->post(route('cuti.pengajuan.surat-izin.resubmit', $pengajuan->id), [
            'perihal' => 'Surat Izin Cuti Tahunan (Diperbaiki)',
            'tujuan_nama' => $pegawai->nama_lengkap,
            'tujuan_instansi' => 'Pengadilan Agama Penajam (Kesekretariatan)',
            'penandatangan' => 'sekretaris',
        ])
        ->assertRedirect();

    expect($surat->fresh()->status)->toBe(StatusSuratDinas::MenungguNomor)
        ->and($gateway->resubmitted)->toHaveCount(1);

    // Simulasikan Admin TU menerbitkan nomor surat di SiSukma
    $issued = kaF1SignedWebhook('outgoing_mail.number_issued', [
        'public_id' => $surat->sisukma_public_id,
        'external_reference' => $surat->id,
        'agenda_number' => $surat->nomor_agenda,
        'mail_number' => '15/SEK.PA.W17-A8/KP5.3/XI/2026',
    ], $secret);
    $this->call('POST', '/api/integrations/sisukma/events', [], [], [], $issued['server'], $issued['rawBody'])
        ->assertStatus(202);

    expect($surat->fresh()->status)->toBe(StatusSuratDinas::NomorTerbit)
        ->and($surat->fresh()->nomor_surat)->toBe('15/SEK.PA.W17-A8/KP5.3/XI/2026')
        ->and($gateway->draftsUploaded)->toHaveCount(1)
        ->and(CutiPengajuanPdf::query()->where('pengajuan_id', $pengajuan->id)->exists())->toBeTrue();

    // Simulasikan Admin TU mengunggah scan bertanda tangan di SiSukma
    $accepted = kaF1SignedWebhook('outgoing_mail.letter_accepted', [
        'public_id' => $surat->sisukma_public_id,
        'external_reference' => $surat->id,
        'file_checksum' => str_repeat('b', 64),
        'file_size_bytes' => 12345,
    ], $secret);
    $this->call('POST', '/api/integrations/sisukma/events', [], [], [], $accepted['server'], $accepted['rawBody'])
        ->assertStatus(202);

    expect($surat->fresh()->status)->toBe(StatusSuratDinas::FinalTersedia)
        ->and($surat->fresh()->final_checksum)->toBe(str_repeat('b', 64));
});

test('cancelAfterApproved membatalkan surat di SiSukma hanya jika nomor belum terbit', function () {
    $gateway = new FakePenomoranGateway;
    $this->app->instance(PenomoranSuratGateway::class, $gateway);
    config(['sisukma.enabled' => true]);

    $pegawai = Pegawai::factory()->admin()->create();
    CutiAlokasiTahunan::create([
        'pegawai_nip' => $pegawai->nip,
        'jenis_cuti_kode' => 'CT',
        'tahun_hak' => now()->year,
        'hak_awal' => 12,
    ]);

    // Kasus 1: masih MenungguNomor -> memanggil gateway->cancel()
    $p1 = CutiPengajuan::factory()->approved()->create([
        'pegawai_nip' => $pegawai->nip,
        'jenis_cuti_kode' => 'CT',
        'tanggal_mulai' => now()->addWeeks(2)->toDateString(),
        'tanggal_selesai' => now()->addWeeks(3)->toDateString(),
        'jumlah_hari_kerja' => 5,
    ]);
    $s1 = SuratDinasRequest::factory()->menungguNomor()->create([
        'suratable_type' => $p1->getMorphClass(),
        'suratable_id' => $p1->id,
    ]);

    app(WorkflowService::class)->cancelAfterApproved($p1->id, $pegawai, 'Batal berangkat');
    expect($s1->fresh()->status)->toBe(StatusSuratDinas::Dibatalkan)
        ->and($gateway->cancelled)->toHaveCount(1);

    // Kasus 2: sudah FinalTersedia -> pencabutan cuti tetap jalan tetapi tidak memanggil cancel API
    $p2 = CutiPengajuan::factory()->approved()->create([
        'pegawai_nip' => $pegawai->nip,
        'jenis_cuti_kode' => 'CT',
        'tanggal_mulai' => now()->addMonths(1)->toDateString(),
        'tanggal_selesai' => now()->addMonths(1)->addDays(3)->toDateString(),
        'jumlah_hari_kerja' => 4,
    ]);
    $s2 = SuratDinasRequest::factory()->finalTersedia()->create([
        'suratable_type' => $p2->getMorphClass(),
        'suratable_id' => $p2->id,
    ]);

    app(WorkflowService::class)->cancelAfterApproved($p2->id, $pegawai, 'Batal mendadak');
    expect($s2->fresh()->status)->toBe(StatusSuratDinas::FinalTersedia)
        ->and($gateway->cancelled)->toHaveCount(1);
});
