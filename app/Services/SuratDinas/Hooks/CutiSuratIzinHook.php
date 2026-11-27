<?php

namespace App\Services\SuratDinas\Hooks;

use App\Actions\Cuti\GenerateSuratIzinCutiPdf;
use App\Jobs\SuratDinas\PushDrafSuratDinasJob;
use App\Models\Cuti\CutiPengajuan;
use App\Models\SuratDinas\SuratDinasRequest;
use App\Notifications\Cuti\SuratIzinFinalTersedia;
use App\Notifications\Cuti\SuratIzinNomorTerbit;
use App\Services\SuratDinas\Contracts\SuratDinasLifecycleHook;

/**
 * Mengelola siklus lanjutan Surat Izin Cuti setelah nomor surat resmi terbit dari SiSukma.
 */
class CutiSuratIzinHook implements SuratDinasLifecycleHook
{
    public function __construct(
        private readonly GenerateSuratIzinCutiPdf $generatePdf,
    ) {}

    public function supports(SuratDinasRequest $request): bool
    {
        return $request->jenis === 'surat_izin_cuti';
    }

    public function onNumberIssued(SuratDinasRequest $request): void
    {
        $pengajuan = $request->suratable;
        if (! $pengajuan instanceof CutiPengajuan || blank($request->nomor_surat)) {
            return;
        }

        $pdfRecord = $this->generatePdf->handle(
            pengajuan: $pengajuan,
            nomorSurat: (string) $request->nomor_surat,
            penandatangan: $request->penandatangan,
        );

        PushDrafSuratDinasJob::dispatch($request->id, $pdfRecord->path_file);

        $pengajuan->loadMissing(['pegawai', 'jenisCuti']);
        $pengajuan->pegawai?->notify(new SuratIzinNomorTerbit($pengajuan, (string) $request->nomor_surat));
    }

    public function onLetterAccepted(SuratDinasRequest $request): void
    {
        $pengajuan = $request->suratable;
        if (! $pengajuan instanceof CutiPengajuan) {
            return;
        }

        $pengajuan->loadMissing(['pegawai', 'jenisCuti']);
        $pengajuan->pegawai?->notify(new SuratIzinFinalTersedia($pengajuan, $request->nomor_surat));
    }
}
