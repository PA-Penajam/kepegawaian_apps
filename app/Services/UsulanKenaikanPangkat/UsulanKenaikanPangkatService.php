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
use App\Services\Sikep\UsulanKenaikanPangkatDto;
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
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Spatie\ModelStates\Exceptions\TransitionNotFound;
use Throwable;

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
        $pegawaiId = (string) $data['pegawai_id'];

        return Cache::lock("usulan-kp:pegawai:{$pegawaiId}", 30)->block(15, function () use ($data, $actor, $pegawaiId): UsulanKenaikanPangkat {
            return DB::transaction(function () use ($data, $actor, $pegawaiId): UsulanKenaikanPangkat {
                $pegawai = Pegawai::query()
                    ->whereKey($pegawaiId)
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->ensurePegawaiEligible($pegawai);

                $riwayatAktif = $pegawai->riwayatPangkat()
                    ->aktif()
                    ->orderByDesc('tmt')
                    ->first();

                $refPangkatAsalId = $riwayatAktif?->ref_pangkat_id ?? ($data['ref_pangkat_asal_id'] ?? null);
                $tmtPangkatAsal = $riwayatAktif?->tmt?->toDateString() ?? ($data['tmt_pangkat_asal'] ?? null);

                if (blank($refPangkatAsalId) || blank($tmtPangkatAsal)) {
                    throw new PegawaiTidakEligibleException('Pegawai belum memiliki riwayat pangkat aktif.');
                }

                $data['ref_pangkat_asal_id'] = $refPangkatAsalId;
                $data['tmt_pangkat_asal'] = $tmtPangkatAsal;

                $this->ensureNoDuplicate($data);

                $id = strtolower((string) Str::ulid());
                $nomorUsulan = filled($data['nomor_usulan'] ?? null)
                    ? (string) $data['nomor_usulan']
                    : $this->generateNomorUsulanFromParts(
                        (int) $data['periode_usul_tahun'],
                        (int) $data['periode_usul_bulan'],
                        $id,
                    );

                $usulan = new UsulanKenaikanPangkat(array_merge($data, [
                    'nomor_usulan' => $nomorUsulan,
                    'state' => DraftState::$name,
                    'created_by' => $actor->id,
                ]));
                $usulan->id = $id;
                $usulan->save();

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
        });
    }

    /**
     * Memperbarui data draft/perlu-perbaikan usulan tanpa mengubah pegawai maupun pangkat asal.
     *
     * @param  array<string, mixed>  $data
     */
    public function update(UsulanKenaikanPangkat $usulan, array $data): UsulanKenaikanPangkat
    {
        $pegawaiId = (string) $usulan->pegawai_id;

        Cache::lock("usulan-kp:tanda-tangan:{$usulan->id}", 120)->block(30, function () use ($usulan, $data, $pegawaiId): void {
            Cache::lock("usulan-kp:pegawai:{$pegawaiId}", 30)->block(15, function () use ($usulan, $data, $pegawaiId): void {
                DB::transaction(function () use ($usulan, $data, $pegawaiId): void {
                    // Kunci baris pegawai dan usulan terlebih dahulu sebelum memvalidasi state dan duplikasi periode aktif.
                    Pegawai::query()
                        ->whereKey($pegawaiId)
                        ->lockForUpdate()
                        ->firstOrFail();

                    $locked = UsulanKenaikanPangkat::query()
                        ->whereKey($usulan->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if (! $locked->state instanceof DraftState && ! $locked->state instanceof PerluPerbaikanState) {
                        throw TransitionNotFound::make((string) $locked->state, DraftState::class, $locked::class);
                    }

                    $this->ensureNoDuplicate([
                        'pegawai_id' => $locked->pegawai_id,
                        'periode_usul_bulan' => $data['periode_usul_bulan'] ?? $locked->periode_usul_bulan,
                        'periode_usul_tahun' => $data['periode_usul_tahun'] ?? $locked->periode_usul_tahun,
                    ], $locked->id);

                    $allowed = array_intersect_key($data, array_flip([
                        'ref_pangkat_tujuan_id',
                        'periode_usul_bulan',
                        'periode_usul_tahun',
                        'catatan_pengusul',
                    ]));

                    $locked->update($allowed);
                });
            });
        });

        return $usulan->refresh();
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
        Cache::lock("usulan-kp:tanda-tangan:{$usulan->id}", 120)->block(30, function () use ($usulan, $actor, $checklist): void {
            DB::transaction(function () use ($usulan, $actor, $checklist): void {
                $locked = UsulanKenaikanPangkat::query()
                    ->whereKey($usulan->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $locked->state->canTransitionTo(DiajukanState::class)) {
                    throw TransitionNotFound::make((string) $locked->state, DiajukanState::class, $locked::class);
                }

                // Baca ulang checklist di dalam lock/transaksi untuk mencegah TOCTOU dengan perubahan checklist.
                $resolvedChecklist = $checklist !== null
                    ? BerkasChecklistSubmission::query()
                        ->with(['template.items', 'items'])
                        ->find($checklist->id)
                    : $locked->checklistSubmission()
                        ->with(['template.items', 'items'])
                        ->first();

                if (! $resolvedChecklist || ! $this->checklistBerkasService->isComplete($resolvedChecklist)) {
                    throw new BerkasBelumLengkapException('Checklist berkas usulan kenaikan pangkat belum lengkap.');
                }

                $resolvedActor = $actor ?? $locked->createdBy()->first() ?? $locked->pegawai()->firstOrFail();

                if ($locked->state instanceof PerluPerbaikanState) {
                    $this->resetApprovalSteps($locked);
                }

                $attributes = [
                    'tanggal_usulan' => now()->toDateString(),
                    'submitted_at' => now(),
                ];

                if (blank($locked->nomor_usulan)) {
                    $attributes['nomor_usulan'] = $this->generateNomorUsulan($locked);
                }

                $locked->forceFill($attributes)->save();
                $this->transition($locked, DiajukanState::class, $resolvedActor, 'submit', 0);
            });
        });

        $usulan->refresh();
    }

    public function verifikasiKasubbag(UsulanKenaikanPangkat $usulan, Pegawai $kasubbag, bool $setuju, ?string $catatan = null): void
    {
        $targetState = $setuju ? DiverifikasiKasubbagState::class : PerluPerbaikanState::class;

        Cache::lock("usulan-kp:tanda-tangan:{$usulan->id}", 120)->block(30, function () use ($usulan, $kasubbag, $setuju, $catatan, $targetState): void {
            DB::transaction(function () use ($usulan, $kasubbag, $setuju, $catatan, $targetState): void {
                $locked = UsulanKenaikanPangkat::query()
                    ->whereKey($usulan->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $locked->state->canTransitionTo($targetState)) {
                    throw TransitionNotFound::make((string) $locked->state, $targetState, $locked::class);
                }

                $this->updateApprovalStep($locked, 1, $kasubbag, $setuju, $catatan);
                $this->transition($locked, $targetState, $kasubbag, $setuju ? 'verifikasi_kasubbag' : 'minta_perbaikan', 1, $catatan);
            });
        });

        $usulan->refresh();
    }

    public function verifikasiSekretaris(UsulanKenaikanPangkat $usulan, Pegawai $sekretaris, bool $setuju, ?string $catatan = null): void
    {
        $targetState = $setuju ? DiverifikasiSekretarisState::class : PerluPerbaikanState::class;

        Cache::lock("usulan-kp:tanda-tangan:{$usulan->id}", 120)->block(30, function () use ($usulan, $sekretaris, $setuju, $catatan, $targetState): void {
            DB::transaction(function () use ($usulan, $sekretaris, $setuju, $catatan, $targetState): void {
                $locked = UsulanKenaikanPangkat::query()
                    ->whereKey($usulan->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $locked->state->canTransitionTo($targetState)) {
                    throw TransitionNotFound::make((string) $locked->state, $targetState, $locked::class);
                }

                $this->updateApprovalStep($locked, 2, $sekretaris, $setuju, $catatan);
                $this->transition($locked, $targetState, $sekretaris, $setuju ? 'verifikasi_sekretaris' : 'minta_perbaikan', 2, $catatan);
            });
        });

        $usulan->refresh();
    }

    public function tandaTanganKetua(UsulanKenaikanPangkat $usulan, Pegawai $ketua): UsulanKpPdf
    {
        return Cache::lock("usulan-kp:tanda-tangan:{$usulan->id}", 120)->block(30, function () use ($usulan, $ketua): UsulanKpPdf {
            // Validasi transisi pada baris terkunci di transaksi singkat sebelum merender PDF di luar transaksi (F-19).
            $lockedBeforePdf = DB::transaction(function () use ($usulan): UsulanKenaikanPangkat {
                $locked = UsulanKenaikanPangkat::query()
                    ->whereKey($usulan->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $locked->state->canTransitionTo(DitandatanganiKetuaState::class)) {
                    throw TransitionNotFound::make((string) $locked->state, DitandatanganiKetuaState::class, $locked::class);
                }

                return $locked;
            });

            if (get_class($this->generateSuratPengantarPdf) === GenerateSuratPengantarPdf::class) {
                $lockedState = null;

                /** @var UsulanKpPdf $pdf */
                $pdf = $this->generateSuratPengantarPdf->handleWithinFinalTransaction(
                    $lockedBeforePdf,
                    $ketua,
                    function () use ($usulan, &$lockedState): void {
                        $lockedState = UsulanKenaikanPangkat::query()
                            ->whereKey($usulan->id)
                            ->lockForUpdate()
                            ->firstOrFail();

                        if (! $lockedState->state->canTransitionTo(DitandatanganiKetuaState::class)) {
                            throw TransitionNotFound::make((string) $lockedState->state, DitandatanganiKetuaState::class, $lockedState::class);
                        }
                    },
                    function () use (&$lockedState, $ketua): void {
                        /** @var UsulanKenaikanPangkat $lockedState */
                        $this->updateApprovalStep($lockedState, 3, $ketua, true, null);
                        $this->transition($lockedState, DitandatanganiKetuaState::class, $ketua, 'tanda_tangan_ketua', 3);
                    },
                );

                $usulan->refresh();

                return $pdf;
            }

            /** @var UsulanKpPdf $pdf */
            $pdf = $this->generateSuratPengantarPdf->handle($lockedBeforePdf, $ketua);

            try {
                return DB::transaction(function () use ($usulan, $ketua, $pdf): UsulanKpPdf {
                    $freshUsulan = UsulanKenaikanPangkat::query()
                        ->whereKey($usulan->id)
                        ->lockForUpdate()
                        ->firstOrFail();

                    if (! $freshUsulan->state->canTransitionTo(DitandatanganiKetuaState::class)) {
                        throw TransitionNotFound::make((string) $freshUsulan->state, DitandatanganiKetuaState::class, $freshUsulan::class);
                    }

                    $this->updateApprovalStep($freshUsulan, 3, $ketua, true, null);
                    $this->transition($freshUsulan, DitandatanganiKetuaState::class, $ketua, 'tanda_tangan_ketua', 3);

                    return $pdf;
                });
            } catch (Throwable $e) {
                if ($pdf instanceof UsulanKpPdf) {
                    $stillReferenced = filled($pdf->file_path) && UsulanKpPdf::query()
                        ->whereKeyNot($pdf->id)
                        ->where('file_path', $pdf->file_path)
                        ->exists();

                    if (filled($pdf->file_path) && ! $stillReferenced && Storage::disk('local')->exists($pdf->file_path)) {
                        Storage::disk('local')->delete($pdf->file_path);
                    }

                    $pdf->delete();
                }

                throw $e;
            }
        });
    }

    public function kirimBiro(UsulanKenaikanPangkat $usulan, Pegawai $actor, ?string $catatan = null): void
    {
        Cache::lock("usulan-kp:tanda-tangan:{$usulan->id}", 120)->block(30, function () use ($usulan, $actor, $catatan): void {
            DB::transaction(function () use ($usulan, $actor, $catatan): void {
                $locked = UsulanKenaikanPangkat::query()
                    ->with(['pegawai', 'pangkatAsal', 'pangkatTujuan'])
                    ->whereKey($usulan->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $locked->state->canTransitionTo(DikirimBiroState::class)) {
                    throw TransitionNotFound::make((string) $locked->state, DikirimBiroState::class, $locked::class);
                }

                $dto = new UsulanKenaikanPangkatDto(
                    nip: (string) ($locked->pegawai?->nip ?? ''),
                    nama_lengkap: (string) ($locked->pegawai?->nama_lengkap ?? ''),
                    pangkat_asal_kode: (string) ($locked->pangkatAsal?->kode ?? ''),
                    pangkat_tujuan_kode: (string) ($locked->pangkatTujuan?->kode ?? ''),
                    tmt_pangkat_asal: $locked->tmt_pangkat_asal?->toDateString() ?? '',
                    periode_bulan: (int) $locked->periode_usul_bulan,
                    periode_tahun: (int) $locked->periode_usul_tahun,
                    nomor_usulan: $locked->nomor_usulan,
                );

                $this->transition($locked, DikirimBiroState::class, $actor, 'kirim_biro', 3, $catatan);
                $this->sikepAdapter->pushUsulan($dto);
                $this->transition($locked->refresh(), MenungguSkState::class, $actor, 'menunggu_sk', 3, $catatan);
            });
        });

        $usulan->refresh();
    }

    public function uploadSkFinal(
        UsulanKenaikanPangkat $usulan,
        Pegawai $actor,
        UploadedFile $skFile,
        string $nomorSk,
        string $tanggalSk,
        ?string $tmtPangkatBaru = null,
        ?int $masaKerjaTahun = null,
        ?int $masaKerjaBulan = null,
    ): void {
        DB::transaction(function () use ($usulan, $actor, $skFile, $nomorSk, $tanggalSk, $tmtPangkatBaru, $masaKerjaTahun, $masaKerjaBulan): void {
            $locked = UsulanKenaikanPangkat::query()
                ->whereKey($usulan->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->state->canTransitionTo(SelesaiSkTerbitState::class)) {
                throw TransitionNotFound::make((string) $locked->state, SelesaiSkTerbitState::class, $locked::class);
            }

            $normalizedOriginalName = str_replace(["\0", '\\'], ['', '/'], trim($skFile->getClientOriginalName()));
            $sanitizedOriginalName = trim(basename($normalizedOriginalName));
            if ($sanitizedOriginalName === '' || $sanitizedOriginalName === '.' || $sanitizedOriginalName === '..') {
                $sanitizedOriginalName = "sk-kenaikan-pangkat-{$locked->id}.pdf";
            }

            $path = "usulan-kp/sk/{$locked->id}.pdf";
            $locked->forceFill([
                'nomor_sk' => $nomorSk,
                'tanggal_sk' => $tanggalSk,
                'tmt_pangkat_baru' => $tmtPangkatBaru,
                'masa_kerja_tahun' => $masaKerjaTahun,
                'masa_kerja_bulan' => $masaKerjaBulan,
                'sk_file_path' => $path,
                'sk_file_original_name' => $sanitizedOriginalName,
                'finalized_at' => now(),
            ])->save();
            $this->transition($locked, SelesaiSkTerbitState::class, $actor, 'upload_sk_final', 3);
            Storage::disk('local')->put($path, $skFile->getContent());
            event(new UsulanKpSkTerbit($locked->refresh(), $tmtPangkatBaru, $masaKerjaTahun, $masaKerjaBulan));
        });

        $usulan->refresh();
    }

    public function tolak(UsulanKenaikanPangkat $usulan, Pegawai $actor, string $alasan): void
    {
        Cache::lock("usulan-kp:tanda-tangan:{$usulan->id}", 120)->block(30, function () use ($usulan, $actor, $alasan): void {
            DB::transaction(function () use ($usulan, $actor, $alasan): void {
                $locked = UsulanKenaikanPangkat::query()
                    ->whereKey($usulan->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $locked->state->canTransitionTo(DitolakState::class)) {
                    throw TransitionNotFound::make((string) $locked->state, DitolakState::class, $locked::class);
                }

                $locked->forceFill(['catatan_penolakan' => $alasan])->save();
                $this->transition($locked, DitolakState::class, $actor, 'tolak', $this->currentStep($locked), $alasan);
            });
        });

        $usulan->refresh();
    }

    public function mintaPerbaikan(UsulanKenaikanPangkat $usulan, Pegawai $actor, string $catatan): void
    {
        Cache::lock("usulan-kp:tanda-tangan:{$usulan->id}", 120)->block(30, function () use ($usulan, $actor, $catatan): void {
            DB::transaction(function () use ($usulan, $actor, $catatan): void {
                $locked = UsulanKenaikanPangkat::query()
                    ->whereKey($usulan->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $locked->state->canTransitionTo(PerluPerbaikanState::class)) {
                    throw TransitionNotFound::make((string) $locked->state, PerluPerbaikanState::class, $locked::class);
                }

                $this->resetApprovalSteps($locked);
                $this->transition($locked, PerluPerbaikanState::class, $actor, 'minta_perbaikan', $this->currentStep($locked), $catatan);
            });
        });

        $usulan->refresh();
    }

    public function batalkan(UsulanKenaikanPangkat $usulan, Pegawai $actor, string $alasan): void
    {
        Cache::lock("usulan-kp:tanda-tangan:{$usulan->id}", 120)->block(30, function () use ($usulan, $actor, $alasan): void {
            DB::transaction(function () use ($usulan, $actor, $alasan): void {
                $locked = UsulanKenaikanPangkat::query()
                    ->whereKey($usulan->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (! $locked->state->canTransitionTo(DibatalkanState::class)) {
                    throw TransitionNotFound::make((string) $locked->state, DibatalkanState::class, $locked::class);
                }

                $this->transition($locked, DibatalkanState::class, $actor, 'batalkan', $this->currentStep($locked), $alasan);
            });
        });

        $usulan->refresh();
    }

    private function ensurePegawaiEligible(Pegawai $pegawai): void
    {
        if ($pegawai->status_pegawai !== StatusPegawai::Aktif || $pegawai->status_kepegawaian === StatusKepegawaian::PPPK) {
            throw new PegawaiTidakEligibleException;
        }
    }

    private function ensureNoDuplicate(array $data, ?string $ignoreUsulanId = null): void
    {
        $query = UsulanKenaikanPangkat::query()
            ->where('pegawai_id', $data['pegawai_id'])
            ->where('periode_usul_bulan', $data['periode_usul_bulan'])
            ->where('periode_usul_tahun', $data['periode_usul_tahun'])
            ->whereIn('state', self::ACTIVE_STATES);

        if ($ignoreUsulanId !== null) {
            $query->where('id', '!=', $ignoreUsulanId);
        }

        if ($query->lockForUpdate()->exists()) {
            throw new DuplicateUsulanException;
        }
    }

    /**
     * Menghasilkan nomor usulan unik berbasis periode dan akhiran ULID.
     */
    private function generateNomorUsulan(UsulanKenaikanPangkat $usulan): string
    {
        return $this->generateNomorUsulanFromParts(
            (int) $usulan->periode_usul_tahun,
            (int) $usulan->periode_usul_bulan,
            (string) $usulan->id,
        );
    }

    private function generateNomorUsulanFromParts(int $tahun, int $bulan, string $id): string
    {
        return sprintf(
            'KP-%04d%02d-%s',
            $tahun,
            $bulan,
            strtoupper(substr($id, -6)),
        );
    }

    /**
     * Mereset seluruh langkah persetujuan ke status menunggu untuk pengajuan ulang.
     */
    private function resetApprovalSteps(UsulanKenaikanPangkat $usulan): void
    {
        $usulan->approvalSteps()->update([
            'approver_user_id' => null,
            'status' => 'menunggu',
            'catatan' => null,
            'acted_at' => null,
        ]);
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
