<?php

namespace App\Services\SuratDinas\Contracts;

use App\Models\SuratDinas\SuratDinasRequest;

interface SuratDinasLifecycleHook
{
    /**
     * Menentukan apakah hook ini menangani jenis permintaan surat dinas tertentu.
     */
    public function supports(SuratDinasRequest $request): bool;

    /**
     * Dipanggil setelah nomor surat resmi terbit dari SiSukma.
     */
    public function onNumberIssued(SuratDinasRequest $request): void;

    /**
     * Dipanggil setelah berkas final bertanda tangan diterima di SiSukma.
     */
    public function onLetterAccepted(SuratDinasRequest $request): void;
}
