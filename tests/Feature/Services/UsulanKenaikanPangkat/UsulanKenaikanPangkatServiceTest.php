<?php

use App\Actions\UsulanKenaikanPangkat\GenerateSuratPengantarPdf;
use App\Enums\StatusKepegawaian;
use App\Enums\StatusPegawai;
use App\Exceptions\UsulanKenaikanPangkat\BerkasBelumLengkapException;
use App\Exceptions\UsulanKenaikanPangkat\DuplicateUsulanException;
use App\Exceptions\UsulanKenaikanPangkat\PegawaiTidakEligibleException;
use App\Models\BerkasChecklist\BerkasChecklistItem;
use App\Models\BerkasChecklist\BerkasChecklistSubmission;
use App\Models\BerkasChecklist\BerkasChecklistSubmissionItem;
use App\Models\BerkasChecklist\BerkasChecklistTemplate;
use App\Models\Pegawai;
use App\Models\RefPangkat;
use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;
use App\Models\UsulanKenaikanPangkat\UsulanKpApproverHistory;
use App\Models\UsulanKenaikanPangkat\UsulanKpPdf;
use App\Models\UsulanKenaikanPangkat\UsulanKpStateHistory;
use App\Services\BerkasChecklist\ChecklistBerkasService;
use App\Services\Sikep\SikepAdapter;
use App\Services\UsulanKenaikanPangkat\UsulanKenaikanPangkatService;
use Database\Seeders\ChecklistKenaikanPangkatSeeder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Mockery\MockInterface;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\FakePdfBuilder;
use Spatie\ModelStates\Exceptions\TransitionNotFound;

uses(RefreshDatabase::class);
beforeEach(fn () => Artisan::call('db:seed', ['--class' => ChecklistKenaikanPangkatSeeder::class, '--force' => true]));
function makePegawaiKp(array $attributes = []): Pegawai
{
    $pangkat = RefPangkat::factory()->create();

    return Pegawai::factory()->create(array_merge([
        'ref_pangkat_id' => $pangkat->id,
        'status_pegawai' => StatusPegawai::Aktif->value,
        'status_kepegawaian' => StatusKepegawaian::PNS->value,
    ], $attributes));
}

function makeUsulanKpData(Pegawai $pegawai, array $attributes = []): array
{
    return array_merge([
        'pegawai_id' => $pegawai->id,
        'ref_pangkat_asal_id' => $pegawai->ref_pangkat_id,
        'ref_pangkat_tujuan_id' => RefPangkat::factory()->create()->id,
        'tmt_pangkat_asal' => now()->subYears(4)->toDateString(),
        'periode_usul_bulan' => 4,
        'periode_usul_tahun' => 2026,
        'catatan_pengusul' => 'Usulan reguler',
    ], $attributes);
}

function makeCompleteChecklist(UsulanKenaikanPangkat $usulan, Pegawai $pegawai): BerkasChecklistSubmission
{
    $usulan->checklistSubmission()->delete();

    $template = BerkasChecklistTemplate::factory()->create(['jenis' => 'kenaikan_pangkat']);
    $item = BerkasChecklistItem::factory()->create([
        'berkas_checklist_template_id' => $template->id,
        'wajib' => true,
    ]);

    $submission = BerkasChecklistSubmission::factory()->create([
        'berkas_checklist_template_id' => $template->id,
        'subject_type' => $usulan->getMorphClass(),
        'subject_id' => $usulan->id,
        'pegawai_id' => $pegawai->id,
        'status_kelengkapan' => 'lengkap',
        'persentase' => 100,
    ]);

    BerkasChecklistSubmissionItem::factory()->create([
        'berkas_checklist_submission_id' => $submission->id,
        'berkas_checklist_item_id' => $item->id,
        'status' => BerkasChecklistSubmissionItem::STATUS_VALID,
    ]);

    return $submission->load('template.items', 'items');
}

function makeIncompleteChecklist(UsulanKenaikanPangkat $usulan, Pegawai $pegawai): BerkasChecklistSubmission
{
    $submission = makeCompleteChecklist($usulan, $pegawai);
    $submission->items()->update(['status' => BerkasChecklistSubmissionItem::STATUS_BELUM_ADA]);

    return $submission->refresh()->load('template.items', 'items');
}

function makeKpService(?GenerateSuratPengantarPdf $pdfAction = null, ?SikepAdapter $adapter = null): UsulanKenaikanPangkatService
{
    $pdfAction ??= Mockery::mock(GenerateSuratPengantarPdf::class, function (MockInterface $mock): void {
        $mock->shouldIgnoreMissing();
    });

    $adapter ??= Mockery::mock(SikepAdapter::class, function (MockInterface $mock): void {
        $mock->shouldReceive('pushUsulan')->byDefault()->andReturn(null);
        $mock->shouldReceive('isConfigured')->byDefault()->andReturn(false);
    });

    return new UsulanKenaikanPangkatService(
        app(ChecklistBerkasService::class),
        $pdfAction,
        $adapter,
    );
}

it('menjalankan happy path end to end sampai SK terbit', function () {
    Storage::fake('local');
    $actor = makePegawaiKp();
    $kasubbag = makePegawaiKp();
    $sekretaris = makePegawaiKp();
    $ketua = makePegawaiKp();
    $service = makeKpService();

    $usulan = $service->createDraft(makeUsulanKpData($actor), $actor);
    $checklist = makeCompleteChecklist($usulan, $actor);
    $service->submit($usulan, $actor, $checklist);
    $service->verifikasiKasubbag($usulan->refresh(), $kasubbag, true, 'Setuju kasubbag');
    $service->verifikasiSekretaris($usulan->refresh(), $sekretaris, true, 'Setuju sekretaris');

    $pdfAction = Mockery::mock(GenerateSuratPengantarPdf::class);
    $pdfAction->shouldReceive('handle')->once()->andReturnUsing(
        fn (UsulanKenaikanPangkat $signedUsulan, Pegawai $pejabat): UsulanKpPdf => UsulanKpPdf::query()->create([
            'usulan_kenaikan_pangkat_id' => $signedUsulan->id,
            'jenis_pdf' => 'surat_pengantar',
            'nomor_surat' => 'KP/001',
            'file_path' => 'usulan-kp/surat-pengantar/'.$signedUsulan->id.'.pdf',
            'generated_by' => $pejabat->id,
            'generated_at' => now(),
        ])
    );
    $adapter = Mockery::mock(SikepAdapter::class);
    $adapter->shouldReceive('pushUsulan')->once()->andReturn(['external_id' => 'SIKEP-1']);
    $adapter->shouldReceive('isConfigured')->byDefault()->andReturn(true);
    $service = makeKpService($pdfAction, $adapter);

    $pdf = $service->tandaTanganKetua($usulan->refresh(), $ketua);
    $service->kirimBiro($usulan->refresh(), $ketua, 'Dikirim');
    $service->uploadSkFinal($usulan->refresh(), $actor, UploadedFile::fake()->create('sk.pdf', 10, 'application/pdf'), 'SK-001', '2026-05-12');

    $usulan->refresh()->load(['stateHistory', 'approverHistory']);
    expect($pdf)->toBeInstanceOf(UsulanKpPdf::class)
        ->and((string) $usulan->state)->toBe('SELESAI_SK_TERBIT')
        ->and($usulan->nomor_sk)->toBe('SK-001')
        ->and($usulan->tanggal_sk->toDateString())->toBe('2026-05-12')
        ->and($usulan->finalized_at)->not->toBeNull()
        ->and($usulan->stateHistory)->toHaveCount(7)
        ->and($usulan->approverHistory)->toHaveCount(7);
    expect(Storage::disk('local')->exists("usulan-kp/sk/{$usulan->id}.pdf"))->toBeTrue();
});

it('membuat draft dengan state draft dan approval steps default', function () {
    $actor = makePegawaiKp();
    $usulan = makeKpService()->createDraft(makeUsulanKpData($actor), $actor);

    expect((string) $usulan->state)->toBe('DRAFT')
        ->and($usulan->created_by)->toBe($actor->id)
        ->and($usulan->approvalSteps()->orderBy('urutan')->pluck('role_required')->all())
        ->toBe(['kasubbag', 'sekretaris', 'ketua']);
});

it('menolak draft untuk pegawai PPPK', function () {
    $actor = makePegawaiKp(['status_kepegawaian' => StatusKepegawaian::PPPK->value]);

    makeKpService()->createDraft(makeUsulanKpData($actor), $actor);
})->throws(PegawaiTidakEligibleException::class);

it('menolak draft untuk pegawai tidak aktif', function () {
    $actor = makePegawaiKp(['status_pegawai' => StatusPegawai::Pensiun->value]);

    makeKpService()->createDraft(makeUsulanKpData($actor), $actor);
})->throws(PegawaiTidakEligibleException::class);

it('menolak duplicate active usulan periode sama', function () {
    $actor = makePegawaiKp();
    $data = makeUsulanKpData($actor);
    UsulanKenaikanPangkat::factory()->create($data);

    makeKpService()->createDraft($data, $actor);
})->throws(DuplicateUsulanException::class);

it('mengizinkan draft baru jika usulan lama terminal ditolak', function () {
    $actor = makePegawaiKp();
    $data = makeUsulanKpData($actor);
    UsulanKenaikanPangkat::factory()->create(array_merge($data, ['state' => 'DITOLAK']));

    $usulan = makeKpService()->createDraft($data, $actor);

    expect($usulan)->toBeInstanceOf(UsulanKenaikanPangkat::class);
});

it('submit menolak checklist belum lengkap', function () {
    $actor = makePegawaiKp();
    $usulan = makeKpService()->createDraft(makeUsulanKpData($actor), $actor);
    $checklist = makeIncompleteChecklist($usulan, $actor);

    makeKpService()->submit($usulan, $actor, $checklist);
})->throws(BerkasBelumLengkapException::class);

it('submit memakai checklist relation jika parameter null', function () {
    $actor = makePegawaiKp();
    $service = makeKpService();
    $usulan = $service->createDraft(makeUsulanKpData($actor), $actor);
    makeCompleteChecklist($usulan, $actor);

    $service->submit($usulan, $actor);

    expect((string) $usulan->refresh()->state)->toBe('DIAJUKAN');
});

it('submit merekam submitted_at tanggal_usulan history dan approver', function () {
    $actor = makePegawaiKp();
    $service = makeKpService();
    $usulan = $service->createDraft(makeUsulanKpData($actor), $actor);
    $checklist = makeCompleteChecklist($usulan, $actor);

    $service->submit($usulan, $actor, $checklist);

    $usulan->refresh()->load(['stateHistory', 'approverHistory']);
    expect($usulan->submitted_at)->not->toBeNull()
        ->and($usulan->tanggal_usulan)->not->toBeNull()
        ->and(UsulanKpStateHistory::query()->where('to_state', 'DIAJUKAN')->count())->toBe(1)
        ->and(UsulanKpApproverHistory::query()->where('action', 'submit')->count())->toBe(1);
});

it('kasubbag setuju memindahkan state ke diverifikasi kasubbag dan update step', function () {
    [$service, $usulan] = prepareSubmittedUsulan();
    $kasubbag = makePegawaiKp();

    $service->verifikasiKasubbag($usulan, $kasubbag, true, 'ok');

    expect((string) $usulan->refresh()->state)->toBe('DIVERIFIKASI_KASUBBAG')
        ->and($usulan->approvalSteps()->where('urutan', 1)->value('status'))->toBe('disetujui');
});

it('kasubbag tidak setuju meminta perbaikan', function () {
    [$service, $usulan] = prepareSubmittedUsulan();

    $service->verifikasiKasubbag($usulan, makePegawaiKp(), false, 'Lengkapi');

    expect((string) $usulan->refresh()->state)->toBe('PERLU_PERBAIKAN');
});

it('sekretaris setuju memindahkan state ke diverifikasi sekretaris dan update step', function () {
    [$service, $usulan] = prepareVerifiedKasubbagUsulan();
    $sekretaris = makePegawaiKp();

    $service->verifikasiSekretaris($usulan, $sekretaris, true, 'ok');

    expect((string) $usulan->refresh()->state)->toBe('DIVERIFIKASI_SEKRETARIS')
        ->and($usulan->approvalSteps()->where('urutan', 2)->value('status'))->toBe('disetujui');
});

it('sekretaris tidak setuju meminta perbaikan', function () {
    [$service, $usulan] = prepareVerifiedKasubbagUsulan();

    $service->verifikasiSekretaris($usulan, makePegawaiKp(), false, 'Revisi');

    expect((string) $usulan->refresh()->state)->toBe('PERLU_PERBAIKAN');
});

it('tanda tangan ketua menghasilkan pdf dan update step ketua', function () {
    [$service, $usulan] = prepareVerifiedSekretarisUsulanWithPdf();

    $pdf = $service->tandaTanganKetua($usulan, makePegawaiKp());

    expect((string) $usulan->refresh()->state)->toBe('DITANDATANGANI_KETUA')
        ->and($pdf)->toBeInstanceOf(UsulanKpPdf::class)
        ->and($usulan->approvalSteps()->where('urutan', 3)->value('status'))->toBe('disetujui');
});

it('kirim biro membuat dua transisi berurutan sampai menunggu SK', function () {
    [$service, $usulan] = prepareSignedUsulan();

    $service->kirimBiro($usulan, makePegawaiKp(), 'Kirim');

    expect((string) $usulan->refresh()->state)->toBe('MENUNGGU_SK')
        ->and($usulan->stateHistory()->where('to_state', 'DIKIRIM_BIRO')->exists())->toBeTrue()
        ->and($usulan->stateHistory()->where('to_state', 'MENUNGGU_SK')->exists())->toBeTrue();
});

it('upload SK final menyimpan file dan data SK', function () {
    Storage::fake('local');
    [$service, $usulan] = prepareWaitingSkUsulan();

    $service->uploadSkFinal($usulan, makePegawaiKp(), UploadedFile::fake()->create('sk-final.pdf', 12, 'application/pdf'), 'SK-777', '2026-05-12');

    $usulan->refresh()->load(['stateHistory', 'approverHistory']);
    expect((string) $usulan->state)->toBe('SELESAI_SK_TERBIT')
        ->and($usulan->sk_file_path)->toBe("usulan-kp/sk/{$usulan->id}.pdf")
        ->and($usulan->sk_file_original_name)->toBe('sk-final.pdf');
    expect(Storage::disk('local')->exists("usulan-kp/sk/{$usulan->id}.pdf"))->toBeTrue();
});

it('tolak memindahkan state ke ditolak dan menyimpan alasan', function () {
    [$service, $usulan] = prepareVerifiedKasubbagUsulan();

    $service->tolak($usulan, makePegawaiKp(), 'Tidak memenuhi syarat');

    expect((string) $usulan->refresh()->state)->toBe('DITOLAK')
        ->and($usulan->catatan_penolakan)->toBe('Tidak memenuhi syarat');
});

it('minta perbaikan memindahkan state ke perlu perbaikan', function () {
    [$service, $usulan] = prepareSubmittedUsulan();

    $service->mintaPerbaikan($usulan, makePegawaiKp(), 'Perbaiki dokumen');

    expect((string) $usulan->refresh()->state)->toBe('PERLU_PERBAIKAN');
});

it('batalkan memindahkan draft ke dibatalkan', function () {
    $actor = makePegawaiKp();
    $service = makeKpService();
    $usulan = $service->createDraft(makeUsulanKpData($actor), $actor);

    $service->batalkan($usulan, $actor, 'Batal');

    expect((string) $usulan->refresh()->state)->toBe('DIBATALKAN');
});

it('rollback DB jika action PDF gagal di tengah transaction', function () {
    [$service, $usulan] = prepareVerifiedSekretarisUsulanWithPdf(shouldFail: true);

    expect(fn () => $service->tandaTanganKetua($usulan, makePegawaiKp()))->toThrow(RuntimeException::class);

    expect((string) $usulan->refresh()->state)->toBe('DIVERIFIKASI_SEKRETARIS')
        ->and($usulan->stateHistory()->where('to_state', 'DITANDATANGANI_KETUA')->exists())->toBeFalse()
        ->and($usulan->approverHistory()->where('action', 'tanda_tangan_ketua')->exists())->toBeFalse();
});

it('activity log tercatat untuk state history dan approver history', function () {
    [, $usulan] = prepareSubmittedUsulan();

    expect($usulan->stateHistory()->count())->toBe(1)
        ->and($usulan->approverHistory()->count())->toBe(1)
        ->and(DB::table('activity_log')->where('subject_type', UsulanKpStateHistory::class)->exists())->toBeTrue()
        ->and(DB::table('activity_log')->where('subject_type', UsulanKpApproverHistory::class)->exists())->toBeTrue();
});

function prepareSubmittedUsulan(): array
{
    $actor = makePegawaiKp();
    $service = makeKpService();
    $usulan = $service->createDraft(makeUsulanKpData($actor), $actor);
    $service->submit($usulan, $actor, makeCompleteChecklist($usulan, $actor));

    return [$service, $usulan->refresh(), $actor];
}

function prepareVerifiedKasubbagUsulan(): array
{
    [$service, $usulan, $actor] = prepareSubmittedUsulan();
    $service->verifikasiKasubbag($usulan, makePegawaiKp(), true, 'ok');

    return [$service, $usulan->refresh(), $actor];
}

function prepareVerifiedSekretarisUsulanWithPdf(bool $shouldFail = false): array
{
    [$baseService, $usulan, $actor] = prepareVerifiedKasubbagUsulan();
    $baseService->verifikasiSekretaris($usulan, makePegawaiKp(), true, 'ok');

    $pdfAction = Mockery::mock(GenerateSuratPengantarPdf::class);
    if ($shouldFail) {
        $pdfAction->shouldReceive('handle')->once()->andThrow(new RuntimeException('PDF gagal'));
    } else {
        $pdfAction->shouldReceive('handle')->andReturnUsing(
            fn (UsulanKenaikanPangkat $signedUsulan, Pegawai $pejabat): UsulanKpPdf => UsulanKpPdf::query()->create([
                'usulan_kenaikan_pangkat_id' => $signedUsulan->id,
                'jenis_pdf' => 'surat_pengantar',
                'nomor_surat' => 'KP/001',
                'file_path' => 'usulan-kp/surat-pengantar/'.$signedUsulan->id.'.pdf',
                'generated_by' => $pejabat->id,
                'generated_at' => now(),
            ])
        );
    }

    return [makeKpService($pdfAction), $usulan->refresh(), $actor];
}

function prepareSignedUsulan(): array
{
    [$service, $usulan, $actor] = prepareVerifiedSekretarisUsulanWithPdf();
    $service->tandaTanganKetua($usulan, makePegawaiKp());

    return [$service, $usulan->refresh(), $actor];
}

function prepareWaitingSkUsulan(): array
{
    [$service, $usulan, $actor] = prepareSignedUsulan();
    $service->kirimBiro($usulan, makePegawaiKp(), 'Kirim');

    return [$service, $usulan->refresh(), $actor];
}

it('mencegah race condition tandaTanganKetua menghapus PDF sah pertama atau membocorkan nomor surat', function () {
    Storage::fake('local');

    Pdf::swap(new class extends FakePdfBuilder
    {
        public function save(string $path): static
        {
            parent::save($path);
            $dir = dirname($path);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($path, '%PDF-1.4 fake');

            return $this;
        }
    });

    [$baseService, $usulan] = prepareVerifiedKasubbagUsulan();
    $baseService->verifikasiSekretaris($usulan, makePegawaiKp(), true, 'OK');

    $realService = new UsulanKenaikanPangkatService(
        app(ChecklistBerkasService::class),
        app(GenerateSuratPengantarPdf::class),
        Mockery::mock(SikepAdapter::class),
    );

    // Dua snapshot stale yang sama-sama berada di state DIVERIFIKASI_SEKRETARIS
    $snapshotPertama = UsulanKenaikanPangkat::query()->findOrFail($usulan->id);
    $snapshotKedua = UsulanKenaikanPangkat::query()->findOrFail($usulan->id);
    $ketua = makePegawaiKp();

    $pdfPertama = $realService->tandaTanganKetua($snapshotPertama, $ketua);

    expect(Storage::disk('local')->exists($pdfPertama->file_path))->toBeTrue();

    expect(fn () => $realService->tandaTanganKetua($snapshotKedua, $ketua))
        ->toThrow(TransitionNotFound::class);

    // File PDF sah pertama harus tetap utuh dan hanya 1 reservasi nomor surat terkonfirmasi
    expect(Storage::disk('local')->exists($pdfPertama->file_path))->toBeTrue()
        ->and(UsulanKpPdf::query()->where('usulan_kenaikan_pangkat_id', $usulan->id)->count())->toBe(1)
        ->and(DB::table('nomor_surat_reservations')->where('status', 'confirmed')->count())->toBe(1);
});

it('mencegah uploadSkFinal stale menimpa file SK sah di disk', function () {
    Storage::fake('local');

    [$service, $usulan] = prepareWaitingSkUsulan();
    $snapshotPertama = UsulanKenaikanPangkat::query()->findOrFail($usulan->id);
    $snapshotKedua = UsulanKenaikanPangkat::query()->findOrFail($usulan->id);
    $adminSk = makePegawaiKp();

    $fileSah = UploadedFile::fake()->createWithContent('sk-sah.pdf', '%PDF-1.4 VALID-SK-1');
    $fileStale = UploadedFile::fake()->createWithContent('sk-stale.pdf', '%PDF-1.4 CORRUPTED-STALE-SK-2');

    $service->uploadSkFinal($snapshotPertama, $adminSk, $fileSah, 'SK-VALID-01', '2026-05-12');

    expect(fn () => $service->uploadSkFinal($snapshotKedua, $adminSk, $fileStale, 'SK-STALE-02', '2026-05-13'))
        ->toThrow(TransitionNotFound::class);

    $path = "usulan-kp/sk/{$usulan->id}.pdf";
    expect(Storage::disk('local')->get($path))->toBe('%PDF-1.4 VALID-SK-1')
        ->and($usulan->fresh()->nomor_sk)->toBe('SK-VALID-01')
        ->and((string) $usulan->fresh()->state)->toBe('SELESAI_SK_TERBIT');
});

it('melepas reservasi nomor surat tanpa bocor confirmed jika usulan ditolak saat render PDF tandaTanganKetua', function () {
    Storage::fake('local');

    [$baseService, $usulan] = prepareVerifiedKasubbagUsulan();
    $baseService->verifikasiSekretaris($usulan, makePegawaiKp(), true, 'OK');
    $ketua = makePegawaiKp();

    Pdf::swap(new class($usulan) extends FakePdfBuilder
    {
        public function __construct(private readonly UsulanKenaikanPangkat $targetUsulan) {}

        public function save(string $path): static
        {
            parent::save($path);
            $dir = dirname($path);
            if (! is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            file_put_contents($path, '%PDF-1.4 fake');

            // Simulasikan state usulan berubah menjadi DITOLAK saat Browsershot sedang merender PDF di luar transaksi
            UsulanKenaikanPangkat::query()
                ->whereKey($this->targetUsulan->id)
                ->update([
                    'state' => 'DITOLAK',
                    'catatan_penolakan' => 'Ditolak bersamaan saat render',
                ]);

            return $this;
        }
    });

    $realService = new UsulanKenaikanPangkatService(
        app(ChecklistBerkasService::class),
        app(GenerateSuratPengantarPdf::class),
        Mockery::mock(SikepAdapter::class),
    );

    expect(fn () => $realService->tandaTanganKetua($usulan->fresh(), $ketua))
        ->toThrow(TransitionNotFound::class);

    expect((string) $usulan->fresh()->state)->toBe('DITOLAK')
        ->and(UsulanKpPdf::query()->where('usulan_kenaikan_pangkat_id', $usulan->id)->count())->toBe(0)
        ->and(Storage::disk('local')->exists("usulan-kp/surat-pengantar/{$usulan->id}.pdf"))->toBeFalse()
        ->and(DB::table('nomor_surat_reservations')->where('status', 'confirmed')->count())->toBe(0)
        ->and(DB::table('nomor_surat_reservations')->where('status', 'released')->count())->toBe(1);
});

it('menolak submit pada snapshot stale setelah usulan dibatalkan dan menjaga state tetap DIBATALKAN', function () {
    $actor = makePegawaiKp();
    $service = makeKpService();
    $usulan = $service->createDraft(makeUsulanKpData($actor), $actor);
    $checklist = makeCompleteChecklist($usulan, $actor);

    $staleDraft = UsulanKenaikanPangkat::query()->findOrFail($usulan->id);

    $service->batalkan($usulan, $actor, 'Dibatalkan lebih dulu');

    expect(fn () => $service->submit($staleDraft, $actor, $checklist))
        ->toThrow(TransitionNotFound::class);

    expect((string) $usulan->fresh()->state)->toBe('DIBATALKAN');
});

it('membaca ulang checklist dari DB di dalam lock submit untuk mencegah TOCTOU', function () {
    $actor = makePegawaiKp();
    $service = makeKpService();
    $usulan = $service->createDraft(makeUsulanKpData($actor), $actor);
    $staleCompleteChecklist = makeCompleteChecklist($usulan, $actor);

    // Simulasikan item checklist berubah menjadi belum lengkap sebelum submit mengambil lock transaksi
    $staleCompleteChecklist->items()->update(['status' => BerkasChecklistSubmissionItem::STATUS_BELUM_ADA]);

    expect(fn () => $service->submit($usulan, $actor, $staleCompleteChecklist))
        ->toThrow(BerkasBelumLengkapException::class);

    expect((string) $usulan->fresh()->state)->toBe('DRAFT');
});

it('menolak verifikasiKasubbag pada snapshot stale setelah dibatalkan atau ditolak', function () {
    [$service, $usulan] = prepareSubmittedUsulan();
    $kasubbag = makePegawaiKp();

    $staleSubmitted = UsulanKenaikanPangkat::query()->findOrFail($usulan->id);
    $service->batalkan($usulan, $kasubbag, 'Batal sebelum verifikasi kasubbag');

    expect(fn () => $service->verifikasiKasubbag($staleSubmitted, $kasubbag, true, 'Setuju terlambat'))
        ->toThrow(TransitionNotFound::class);

    expect((string) $usulan->fresh()->state)->toBe('DIBATALKAN');
});

it('menolak verifikasiSekretaris pada snapshot stale setelah ditolak', function () {
    [$service, $usulan] = prepareVerifiedKasubbagUsulan();
    $sekretaris = makePegawaiKp();

    $staleVerified = UsulanKenaikanPangkat::query()->findOrFail($usulan->id);
    $service->tolak($usulan, $sekretaris, 'Ditolak lebih dulu');

    expect(fn () => $service->verifikasiSekretaris($staleVerified, $sekretaris, true, 'Setuju terlambat'))
        ->toThrow(TransitionNotFound::class);

    expect((string) $usulan->fresh()->state)->toBe('DITOLAK');
});

it('menolak kirimBiro pada snapshot stale dan tidak mengirim dua kali ke SIKEP', function () {
    [, $usulan] = prepareVerifiedSekretarisUsulanWithPdf();
    $ketua = makePegawaiKp();
    $biroActor = makePegawaiKp();

    $pdfAction = Mockery::mock(GenerateSuratPengantarPdf::class);
    $pdfAction->shouldReceive('handle')->andReturnUsing(
        fn (UsulanKenaikanPangkat $signedUsulan, Pegawai $pejabat): UsulanKpPdf => UsulanKpPdf::query()->create([
            'usulan_kenaikan_pangkat_id' => $signedUsulan->id,
            'jenis_pdf' => 'surat_pengantar',
            'nomor_surat' => 'KP/002',
            'file_path' => 'usulan-kp/surat-pengantar/'.$signedUsulan->id.'.pdf',
            'generated_by' => $pejabat->id,
            'generated_at' => now(),
        ])
    );

    $adapter = Mockery::mock(SikepAdapter::class);
    $adapter->shouldReceive('pushUsulan')->once()->andReturn(['external_id' => 'SIKEP-ONCE']);
    $adapter->shouldReceive('isConfigured')->byDefault()->andReturn(true);

    $service = makeKpService($pdfAction, $adapter);
    $service->tandaTanganKetua($usulan->fresh(), $ketua);

    $snapshotPertama = UsulanKenaikanPangkat::query()->findOrFail($usulan->id);
    $snapshotKedua = UsulanKenaikanPangkat::query()->findOrFail($usulan->id);

    $service->kirimBiro($snapshotPertama, $biroActor, 'Kirim pertama');

    expect(fn () => $service->kirimBiro($snapshotKedua, $biroActor, 'Kirim duplikat'))
        ->toThrow(TransitionNotFound::class);

    expect((string) $usulan->fresh()->state)->toBe('MENUNGGU_SK')
        ->and($usulan->approverHistory()->where('action', 'kirim_biro')->count())->toBe(1);
});

it('createDraft membungkus pengecekan eligible dan duplikasi di dalam transaksi serta rollback penuh jika attachChecklist gagal', function () {
    $actor = makePegawaiKp();
    $data = makeUsulanKpData($actor);
    $observedTransactionLevel = 0;

    DB::listen(function ($query) use (&$observedTransactionLevel): void {
        if (str_contains(strtolower($query->sql), 'from "pegawai"') || str_contains(strtolower($query->sql), 'from `pegawai`')) {
            $observedTransactionLevel = max($observedTransactionLevel, DB::transactionLevel());
        }
    });

    // Hapus template aktif agar attachChecklist gagal setelah pengecekan duplikat & insert awal
    BerkasChecklistTemplate::query()->delete();

    expect(fn () => makeKpService()->createDraft($data, $actor))
        ->toThrow(ModelNotFoundException::class);

    expect($observedTransactionLevel)->toBeGreaterThanOrEqual(1)
        ->and(UsulanKenaikanPangkat::query()->where('pegawai_id', $actor->id)->count())->toBe(0);
});

it('update mengunci baris usulan dalam transaksi dan menolak periode yang bertabrakan dengan usulan aktif lain', function () {
    $actor = makePegawaiKp();
    $service = makeKpService();

    $usulanApril = $service->createDraft(makeUsulanKpData($actor, [
        'periode_usul_bulan' => 4,
        'periode_usul_tahun' => 2026,
    ]), $actor);

    $usulanOktober = $service->createDraft(makeUsulanKpData($actor, [
        'periode_usul_bulan' => 10,
        'periode_usul_tahun' => 2026,
    ]), $actor);

    expect(fn () => $service->update($usulanOktober, [
        'periode_usul_bulan' => 4,
        'periode_usul_tahun' => 2026,
        'catatan_pengusul' => 'Tabrakan periode',
    ]))->toThrow(DuplicateUsulanException::class);

    expect((int) $usulanOktober->fresh()->periode_usul_bulan)->toBe(10)
        ->and($usulanOktober->fresh()->catatan_pengusul)->toBe('Usulan reguler')
        ->and($usulanApril->fresh()->periode_usul_bulan)->toBe(4);
});

it('update menolak usulan yang sudah berstatus DIAJUKAN di dalam lock transaksi tanpa memutasi data', function () {
    [$service, $usulan] = prepareSubmittedUsulan();

    $catatanAwal = $usulan->catatan_pengusul;
    $pangkatTujuanAwal = $usulan->ref_pangkat_tujuan_id;
    $pangkatTujuanBaru = RefPangkat::factory()->create();

    expect(fn () => $service->update($usulan, [
        'ref_pangkat_tujuan_id' => $pangkatTujuanBaru->id,
        'catatan_pengusul' => 'Mutasi ilegal setelah submit',
    ]))->toThrow(TransitionNotFound::class);

    expect((string) $usulan->fresh()->state)->toBe('DIAJUKAN')
        ->and($usulan->fresh()->catatan_pengusul)->toBe($catatanAwal)
        ->and($usulan->fresh()->ref_pangkat_tujuan_id)->toBe($pangkatTujuanAwal);
});
