<?php

namespace App\Services\SuratDinas\Contracts;

use App\Models\SuratDinas\SuratDinasRequest;
use App\Services\SuratDinas\DTO\RegistrationResult;
use App\Services\SuratDinas\DTO\RemoteMailStatus;
use Symfony\Component\HttpFoundation\StreamedResponse;

interface PenomoranSuratGateway
{
    /**
     * Mendaftarkan surat keluar ke SiSukma secara idempoten berdasarkan ID permintaan.
     */
    public function register(SuratDinasRequest $request): RegistrationResult;

    /**
     * Mengajukan ulang surat keluar yang berstatus perlu perbaikan beserta pembaruan metadata.
     */
    public function resubmit(SuratDinasRequest $request): void;

    /**
     * Membatalkan surat keluar yang belum diterbitkan nomor resminya di SiSukma.
     */
    public function cancel(SuratDinasRequest $request, string $reason): void;

    /**
     * Mengirim PDF draf bernomor ke SiSukma untuk menggantikan `file_draft`.
     */
    public function replaceDraft(SuratDinasRequest $request, string $absolutePath): void;

    /**
     * Men-stream berkas surat final dari SiSukma sebagai respons HTTP unduhan/pratinjau.
     */
    public function streamFinal(SuratDinasRequest $request, string $downloadFilename): StreamedResponse;

    /**
     * Mengambil status terkini surat keluar di SiSukma untuk keperluan rekonsiliasi.
     */
    public function fetchStatus(SuratDinasRequest $request): RemoteMailStatus;

    /**
     * Mencari surat masuk non-rahasia di SiSukma untuk ditautkan ke perizinan eksternal.
     *
     * @return list<array<string, mixed>>
     */
    public function searchIncomingMails(string $query): array;

    /**
     * Menautkan surat masuk SiSukma dengan pengajuan cuti eksternal secara idempoten.
     *
     * @return array<string, mixed>
     */
    public function linkIncomingMail(
        string $publicId,
        string $externalReference,
        string $externalLabel,
        string $externalPath,
        string $linkedByNip,
    ): array;

    /**
     * Men-stream berkas scan surat masuk yang telah ditautkan di SiSukma.
     */
    public function streamIncomingMailFile(string $publicId, string $downloadFilename): StreamedResponse;
}
