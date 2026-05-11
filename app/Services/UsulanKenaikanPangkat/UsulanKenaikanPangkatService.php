<?php

namespace App\Services\UsulanKenaikanPangkat;

use App\Actions\UsulanKenaikanPangkat\GenerateSuratPengantarPdf;
use App\Enums\StatusKepegawaian;
use App\Enums\StatusPegawai;
use App\Events\UsulanKenaikanPangkat\UsulanKpSkTerbit;
use App\Exceptions\UsulanKenaikanPangkat\BerkasBelumLengkapException;
use App\Exceptions\UsulanKenaikanPangkat\DuplicateUsulanException;
use App\Exceptions\UsulanKenaikanPangkat\PegawaiTidakEligibleException;
use App\Models\BerkasChecklist\BerkasChecklistSubmission;
use App\Models\BerkasChecklist\BerkasChecklistTemplate;
use App\Models\Pegawai;
use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;
use App\Models\UsulanKenaikanPangkat\UsulanKpPdf;
use App\Services\BerkasChecklist\ChecklistBerkasService;
use App\Services\Sikep\SikepAdapter;
use App\States\UsulanKenaikanPangkat\DiajukanState;
use App\States\UsulanKenaikanPangkat\DibatalkanState;
use App\States\UsulanKenaikanPangkat\DikirimBiroState;
use App\States\UsulanKenaikanPangkat\DitandatanganiKetuaState;
use App\States\UsulanKenaikanPangkat\DitolakState;
use App\States\UsulanKenaikanPangkat\DiverifikasiKasubbagState;
use App\States\UsulanKenaikanPangkat\DiverifikasiSekretarisState;
use App\States\UsulanKenaikanPangkat\DraftState;
use App\States\UsulanKenaikanPangkat\MenungguSkState;
use App\States\UsulanKenaikanPangkat\PerluPerbaikanState;
use App\States\UsulanKenaikanPangkat\SelesaiSkTerbitState;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class UsulanKenaikanPangkatService
{
    private const ACTIVE_STATES = ['DRAFT', 'DIAJUKAN', 'DIVERIFIKASI_KASUBBAG', 'DIVERIFIKASI_SEKRETARIS', 'DITANDATANGANI_KETUA', 'DIKIRIM_BIRO', 'MENUNGGU_SK', 'PERLU_PERBAIKAN'];

    public function __construct(
        private readonly ChecklistBerkasService $checklistBerkasService,
        private readonly GenerateSuratPengantarPdf $generateSuratPengantarPdf,
        private readonly SikepAdapter $sikepAdapter,
    ) {}

    public function createDraft(array $data, Pegawai $actor): UsulanKenaikanPangkat
    {
        $pegawai = Pegawai::query()->findOrFail($data['pegawai_id']);
        $this->ensurePegawaiEligible($pegawai);
        $this->ensureNoDuplicate($data);

        return DB::transaction(function () use ($data, $actor): UsulanKenaikanPangkat {
            $usulan = UsulanKenaikanPangkat::query()->create(array_merge($data, [
                'state' => DraftState::$name,
                'created_by' => $actor->id,
            ]));

            foreach ([1 => 'kasubbag', 2 => 'sekretaris', 3 => 'ketua'] as $urutan => $role) {
                $usulan->approvalSteps()->create(['urutan' => $urutan, 'role_required' => $role, 'status' => 'menunggu']);
            }

            $template = BerkasChecklistTemplate::query()
                ->aktif()
                ->where('kode', config('sikep.kp.checklist_template_kode'))
                ->firstOrFail();

            $this->attachChecklist($usulan, $template, $actor);

            return $usulan->load(['approvalSteps', 'checklistSubmission.items']);
        });
    }

    public function attachChecklist(UsulanKenaikanPangkat $usulan, BerkasChecklistTemplate $template, Pegawai $pegawai): BerkasChecklistSubmission
    {
        $existingSubmission = $usulan->checklistSubmission()->first();

        if ($existingSubmission instanceof BerkasChecklistSubmission) {
            return $existingSubmission->load('items');
        }

        return $this->checklistBerkasService->createSubmission($template, $usulan, $pegawai);
    }

    public function submit(UsulanKenaikanPangkat $usulan, ?Pegawai $actor = null, ?BerkasChecklistSubmission $checklist = null): void
    {
        $actor ??= $usulan->createdBy()->first() ?? $usulan->pegawai()->firstOrFail();
        $checklist ??= $usulan->checklistSubmission()->with(['template.items', 'items'])->first();

        if (! $checklist || ! $this->checklistBerkasService->isComplete($checklist)) {
            throw new BerkasBelumLengkapException('Checklist berkas usulan kenaikan pangkat belum lengkap.');
        }

        DB::transaction(function () use ($usulan, $actor): void {
            $usulan->forceFill(['tanggal_usulan' => now()->toDateString(), 'submitted_at' => now()])->save();
            $this->transition($usulan, DiajukanState::class, $actor, 'submit', 0);
        });
    }

    public function verifikasiKasubbag(UsulanKenaikanPangkat $usulan, Pegawai $kasubbag, bool $setuju, ?string $catatan = null): void
    {
        DB::transaction(function () use ($usulan, $kasubbag, $setuju, $catatan): void {
            $this->updateApprovalStep($usulan, 1, $kasubbag, $setuju, $catatan);
            $this->transition($usulan, $setuju ? DiverifikasiKasubbagState::class : PerluPerbaikanState::class, $kasubbag, $setuju ? 'verifikasi_kasubbag' : 'minta_perbaikan', 1, $catatan);
        });
    }

    public function verifikasiSekretaris(UsulanKenaikanPangkat $usulan, Pegawai $sekretaris, bool $setuju, ?string $catatan = null): void
    {
        DB::transaction(function () use ($usulan, $sekretaris, $setuju, $catatan): void {
            $this->updateApprovalStep($usulan, 2, $sekretaris, $setuju, $catatan);
            $this->transition($usulan, $setuju ? DiverifikasiSekretarisState::class : PerluPerbaikanState::class, $sekretaris, $setuju ? 'verifikasi_sekretaris' : 'minta_perbaikan', 2, $catatan);
        });
    }

    public function tandaTanganKetua(UsulanKenaikanPangkat $usulan, Pegawai $ketua): UsulanKpPdf
    {
        return DB::transaction(function () use ($usulan, $ketua): UsulanKpPdf {
            $this->updateApprovalStep($usulan, 3, $ketua, true, null);
            $this->transition($usulan, DitandatanganiKetuaState::class, $ketua, 'tanda_tangan_ketua', 3);

            return $this->generateSuratPengantarPdf->handle($usulan->refresh(), $ketua);
        });
    }

    public function kirimBiro(UsulanKenaikanPangkat $usulan, Pegawai $actor, ?string $catatan = null): void
    {
        DB::transaction(function () use ($usulan, $actor, $catatan): void {
            $this->transition($usulan, DikirimBiroState::class, $actor, 'kirim_biro', 3, $catatan);
            $this->sikepAdapter->pushUsulan((object) ['id' => $usulan->id, 'jenis' => 'kenaikan_pangkat']);
            $this->transition($usulan->refresh(), MenungguSkState::class, $actor, 'menunggu_sk', 3, $catatan);
        });
    }

    public function uploadSkFinal(UsulanKenaikanPangkat $usulan, Pegawai $actor, UploadedFile $skFile, string $nomorSk, string $tanggalSk): void
    {
        DB::transaction(function () use ($usulan, $actor, $skFile, $nomorSk, $tanggalSk): void {
            $path = "usulan-kp/sk/{$usulan->id}.pdf";
            Storage::disk('local')->put($path, $skFile->getContent());
            $usulan->forceFill(['nomor_sk' => $nomorSk, 'tanggal_sk' => $tanggalSk, 'sk_file_path' => $path, 'sk_file_original_name' => $skFile->getClientOriginalName(), 'finalized_at' => now()])->save();
            $this->transition($usulan, SelesaiSkTerbitState::class, $actor, 'upload_sk_final', 3);
            event(new UsulanKpSkTerbit($usulan->refresh()));
        });
    }

    public function tolak(UsulanKenaikanPangkat $usulan, Pegawai $actor, string $alasan): void
    {
        DB::transaction(function () use ($usulan, $actor, $alasan): void {
            $usulan->forceFill(['catatan_penolakan' => $alasan])->save();
            $this->transition($usulan, DitolakState::class, $actor, 'tolak', $this->currentStep($usulan), $alasan);
        });
    }

    public function mintaPerbaikan(UsulanKenaikanPangkat $usulan, Pegawai $actor, string $catatan): void
    {
        DB::transaction(fn () => $this->transition($usulan, PerluPerbaikanState::class, $actor, 'minta_perbaikan', $this->currentStep($usulan), $catatan));
    }

    public function batalkan(UsulanKenaikanPangkat $usulan, Pegawai $actor, string $alasan): void
    {
        DB::transaction(fn () => $this->transition($usulan, DibatalkanState::class, $actor, 'batalkan', $this->currentStep($usulan), $alasan));
    }

    private function ensurePegawaiEligible(Pegawai $pegawai): void
    {
        if ($pegawai->status_pegawai !== StatusPegawai::Aktif || $pegawai->status_kepegawaian === StatusKepegawaian::PPPK) {
            throw new PegawaiTidakEligibleException;
        }
    }

    private function ensureNoDuplicate(array $data): void
    {
        if (UsulanKenaikanPangkat::query()->where('pegawai_id', $data['pegawai_id'])->where('periode_usul_bulan', $data['periode_usul_bulan'])->where('periode_usul_tahun', $data['periode_usul_tahun'])->whereIn('state', self::ACTIVE_STATES)->exists()) {
            throw new DuplicateUsulanException;
        }
    }

    /**
     * @param  class-string  $targetState
     */
    private function transition(UsulanKenaikanPangkat $usulan, string $targetState, Pegawai $actor, string $action, int $step, ?string $catatan = null): void
    {
        $fromState = (string) $usulan->state;
        $toState = $targetState::$name;
        $usulan->stateHistory()->create(['from_state' => $fromState, 'to_state' => $toState, 'transitioned_by' => $actor->id, 'catatan' => $catatan]);
        $usulan->approverHistory()->create(['step_urutan' => $step, 'user_id' => $actor->id, 'action' => $action, 'catatan' => $catatan, 'meta' => ['from_state' => $fromState, 'to_state' => $toState]]);
        $usulan->state->transitionTo($targetState);
    }

    private function updateApprovalStep(UsulanKenaikanPangkat $usulan, int $urutan, Pegawai $actor, bool $setuju, ?string $catatan): void
    {
        $usulan->approvalSteps()->where('urutan', $urutan)->update(['approver_user_id' => $actor->id, 'status' => $setuju ? 'disetujui' : 'perlu_perbaikan', 'catatan' => $catatan, 'acted_at' => now()]);
    }

    private function currentStep(UsulanKenaikanPangkat $usulan): int
    {
        return match ((string) $usulan->state) {
            'DIAJUKAN', 'DIVERIFIKASI_KASUBBAG' => 1,
            'DIVERIFIKASI_SEKRETARIS' => 2,
            'DITANDATANGANI_KETUA', 'DIKIRIM_BIRO', 'MENUNGGU_SK' => 3,
            default => 0,
        };
    }
}
