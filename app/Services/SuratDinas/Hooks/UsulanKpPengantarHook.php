<?php

namespace App\Services\SuratDinas\Hooks;

use App\Actions\UsulanKenaikanPangkat\GenerateSuratPengantarPdf;
use App\Jobs\SuratDinas\PushDrafSuratDinasJob;
use App\Models\Pegawai;
use App\Models\SuratDinas\SuratDinasRequest;
use App\Models\UsulanKenaikanPangkat\UsulanKenaikanPangkat;
use App\Services\SuratDinas\Contracts\SuratDinasLifecycleHook;
use App\Services\UsulanKenaikanPangkat\UsulanKenaikanPangkatService;

/**
 * Mengelola pencetakan draf surat pengantar KP dan penyelesaian tanda tangan Ketua via SiSukma.
 */
class UsulanKpPengantarHook implements SuratDinasLifecycleHook
{
    public function __construct(
        private readonly GenerateSuratPengantarPdf $generatePdf,
        private readonly UsulanKenaikanPangkatService $usulanService,
    ) {}

    public function supports(SuratDinasRequest $request): bool
    {
        return $request->jenis === 'pengantar_kp';
    }

    public function onNumberIssued(SuratDinasRequest $request): void
    {
        $usulan = $request->suratable;
        if (! $usulan instanceof UsulanKenaikanPangkat || blank($request->nomor_surat)) {
            return;
        }

        $pejabat = Pegawai::query()->where('nip', $request->requested_by_nip)->first()
            ?? $usulan->pegawai;

        if (! $pejabat instanceof Pegawai) {
            return;
        }

        $pdf = $this->generatePdf->renderForExistingNumber(
            usulan: $usulan,
            pejabatPenandatangan: $pejabat,
            nomorSurat: (string) $request->nomor_surat,
        );

        PushDrafSuratDinasJob::dispatch($request->id, $pdf->file_path);
    }

    public function onLetterAccepted(SuratDinasRequest $request): void
    {
        $usulan = $request->suratable;
        if (! $usulan instanceof UsulanKenaikanPangkat) {
            return;
        }

        $actor = Pegawai::query()->where('nip', $request->requested_by_nip)->first()
            ?? $usulan->pegawai;

        if ($actor instanceof Pegawai) {
            $this->usulanService->completeSigningFromSisukma($usulan, $actor);
        }
    }
}
