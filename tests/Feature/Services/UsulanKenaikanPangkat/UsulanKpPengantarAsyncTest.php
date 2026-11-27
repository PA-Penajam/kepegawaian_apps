<?php

use App\Enums\StatusKepegawaian;
use App\Enums\StatusPegawai;
use App\Enums\StatusSuratDinas;
use App\Models\BerkasChecklist\BerkasChecklistItem;
use App\Models\BerkasChecklist\BerkasChecklistSubmission;
use App\Models\BerkasChecklist\BerkasChecklistSubmissionItem;
use App\Models\BerkasChecklist\BerkasChecklistTemplate;
use App\Models\Pegawai;
use App\Models\RefPangkat;
use App\Models\SuratDinas\SuratDinasRequest;
use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;
use App\Models\UsulanKenaikanPangkat\UsulanKpPdf;
use App\Services\SuratDinas\Contracts\PenomoranSuratGateway;
use App\Services\SuratDinas\FakePenomoranGateway;
use App\Services\UsulanKenaikanPangkat\UsulanKenaikanPangkatService;
use Database\Seeders\ChecklistKenaikanPangkatSeeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\LaravelPdf\Facades\Pdf;
use Spatie\LaravelPdf\FakePdfBuilder;
use Spatie\ModelStates\Exceptions\TransitionNotFound;

beforeEach(function () {
    Artisan::call('db:seed', ['--class' => ChecklistKenaikanPangkatSeeder::class, '--force' => true]);
});

test('saat sisukma enabled, tandaTanganKetua masuk MENUNGGU_NOMOR_SURAT lalu selesai ke DITANDATANGANI_KETUA setelah letter_accepted', function () {
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

            file_put_contents($path, "%PDF-1.4\n%%EOF\n");

            return $this;
        }
    });

    $gateway = new FakePenomoranGateway;
    $this->app->instance(PenomoranSuratGateway::class, $gateway);

    $secret = 'secret-kp-async';
    config([
        'sisukma.enabled' => true,
        'sisukma.webhook_secret' => $secret,
    ]);

    $pangkatAsal = RefPangkat::factory()->create();
    $pangkatTujuan = RefPangkat::factory()->create();
    $pemohon = Pegawai::factory()->admin()->create([
        'ref_pangkat_id' => $pangkatAsal->id,
        'status_pegawai' => StatusPegawai::Aktif->value,
        'status_kepegawaian' => StatusKepegawaian::PNS->value,
    ]);
    $ketua = Pegawai::factory()->admin()->create();

    $usulan = UsulanKenaikanPangkat::factory()->create([
        'pegawai_id' => $pemohon->id,
        'ref_pangkat_asal_id' => $pangkatAsal->id,
        'ref_pangkat_tujuan_id' => $pangkatTujuan->id,
        'state' => 'DIVERIFIKASI_SEKRETARIS',
        'created_by' => $pemohon->id,
    ]);
    $usulan->approvalSteps()->create(['urutan' => 3, 'role_required' => 'ketua', 'status' => 'menunggu']);

    $template = BerkasChecklistTemplate::factory()->create(['jenis' => 'kenaikan_pangkat']);
    $item = BerkasChecklistItem::factory()->create(['berkas_checklist_template_id' => $template->id, 'wajib' => true]);
    $submission = BerkasChecklistSubmission::factory()->create([
        'berkas_checklist_template_id' => $template->id,
        'subject_type' => $usulan->getMorphClass(),
        'subject_id' => $usulan->id,
        'pegawai_id' => $pemohon->id,
        'status_kelengkapan' => 'lengkap',
        'persentase' => 100,
    ]);
    BerkasChecklistSubmissionItem::factory()->create([
        'berkas_checklist_submission_id' => $submission->id,
        'berkas_checklist_item_id' => $item->id,
        'status' => BerkasChecklistSubmissionItem::STATUS_VALID,
    ]);

    $service = app(UsulanKenaikanPangkatService::class);
    $result = $service->tandaTanganKetua($usulan->refresh(), $ketua);

    expect($result)->toBeNull()
        ->and((string) $usulan->fresh()->state)->toBe('MENUNGGU_NOMOR_SURAT');

    $surat = SuratDinasRequest::query()
        ->where('suratable_id', $usulan->id)
        ->firstOrFail();

    expect($surat->jenis)->toBe('pengantar_kp')
        ->and($surat->klasifikasi)->toBe('KP2.1.1')
        ->and($surat->status)->toBe(StatusSuratDinas::MenungguNomor)
        ->and($gateway->registered)->toHaveCount(1);

    // kirimBiro ditolak sebelum surat final tersedia
    expect(fn () => $service->kirimBiro($usulan->fresh(), $ketua))
        ->toThrow(TransitionNotFound::class);

    // Webhook 1: number_issued -> generate draf KP bernomor & unggah ke SiSukma
    $eventId1 = (string) Str::uuid();
    $ts1 = (string) now()->timestamp;
    $body1 = json_encode([
        'event_id' => $eventId1,
        'event_type' => 'outgoing_mail.number_issued',
        'occurred_at' => now()->toIso8601String(),
        'data' => [
            'public_id' => $surat->sisukma_public_id,
            'external_reference' => $surat->id,
            'agenda_number' => $surat->nomor_agenda,
            'mail_number' => '45/KPA.W17-A8/KP2.1.1/XI/2026',
        ],
    ], JSON_THROW_ON_ERROR);
    $this->call('POST', '/api/integrations/sisukma/events', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_EVENT_ID' => $eventId1,
        'HTTP_X_TIMESTAMP' => $ts1,
        'HTTP_X_SIGNATURE' => hash_hmac('sha256', "{$eventId1}.{$ts1}.{$body1}", $secret),
    ], $body1)->assertStatus(202);

    $kpPdf = UsulanKpPdf::query()->where('usulan_kenaikan_pangkat_id', $usulan->id)->first();
    expect($kpPdf)->not->toBeNull()
        ->and($kpPdf->nomor_surat)->toBe('45/KPA.W17-A8/KP2.1.1/XI/2026')
        ->and($gateway->draftsUploaded)->toHaveCount(1)
        ->and((string) $usulan->fresh()->state)->toBe('MENUNGGU_NOMOR_SURAT');

    // Webhook 2: letter_accepted -> transisi ke DITANDATANGANI_KETUA
    $eventId2 = (string) Str::uuid();
    $ts2 = (string) now()->timestamp;
    $body2 = json_encode([
        'event_id' => $eventId2,
        'event_type' => 'outgoing_mail.letter_accepted',
        'occurred_at' => now()->toIso8601String(),
        'data' => [
            'public_id' => $surat->sisukma_public_id,
            'external_reference' => $surat->id,
            'file_checksum' => str_repeat('c', 64),
            'file_size_bytes' => 54321,
        ],
    ], JSON_THROW_ON_ERROR);
    $this->call('POST', '/api/integrations/sisukma/events', [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_ACCEPT' => 'application/json',
        'HTTP_X_EVENT_ID' => $eventId2,
        'HTTP_X_TIMESTAMP' => $ts2,
        'HTTP_X_SIGNATURE' => hash_hmac('sha256', "{$eventId2}.{$ts2}.{$body2}", $secret),
    ], $body2)->assertStatus(202);

    expect((string) $usulan->fresh()->state)->toBe('DITANDATANGANI_KETUA');

    // Endpoint download surat pengantar men-stream berkas final dari SiSukma
    $this->actingAs($pemohon)
        ->get(route('kenaikan-pangkat.admin-sk.download-surat-pengantar', $kpPdf->id))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf');

    // Sekarang kirimBiro berhasil
    $service->kirimBiro($usulan->fresh(), $ketua);
    expect((string) $usulan->fresh()->state)->toBe('MENUNGGU_SK');
});
