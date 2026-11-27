<?php

namespace App\Services\SuratDinas;

use App\Models\SuratDinas\SuratDinasRequest;
use App\Services\SuratDinas\Contracts\PenomoranSuratGateway;
use App\Services\SuratDinas\DTO\RegistrationResult;
use App\Services\SuratDinas\DTO\RemoteMailStatus;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Implementasi palsu gateway penomoran untuk pengembangan offline dan pengujian otomatis.
 */
class FakePenomoranGateway implements PenomoranSuratGateway
{
    /** @var list<array<string, mixed>> */
    public array $registered = [];

    /** @var list<string> */
    public array $resubmitted = [];

    /** @var list<array{id: string, reason: string}> */
    public array $cancelled = [];

    /** @var list<array{id: string, path: string}> */
    public array $draftsUploaded = [];

    /** @var list<array<string, string>> */
    public array $incomingLinks = [];

    private int $sequence = 1;

    public function register(SuratDinasRequest $request): RegistrationResult
    {
        $publicId = (string) Str::ulid();
        $agenda = ($this->sequence++).'/'.now()->year;

        $this->registered[] = [
            'request_id' => $request->id,
            'public_id' => $publicId,
            'agenda_number' => $agenda,
            'klasifikasi' => $request->klasifikasi,
        ];

        return new RegistrationResult(
            publicId: $publicId,
            agendaNumber: $agenda,
            status: 'registered',
        );
    }

    public function resubmit(SuratDinasRequest $request): void
    {
        $this->resubmitted[] = $request->id;
    }

    public function cancel(SuratDinasRequest $request, string $reason): void
    {
        $this->cancelled[] = [
            'id' => $request->id,
            'reason' => $reason,
        ];
    }

    public function replaceDraft(SuratDinasRequest $request, string $absolutePath): void
    {
        $this->draftsUploaded[] = [
            'id' => $request->id,
            'path' => $absolutePath,
        ];
    }

    public function streamFinal(SuratDinasRequest $request, string $downloadFilename): StreamedResponse
    {
        $safeFilename = str_replace('"', '', basename($downloadFilename));

        return response()->stream(function (): void {
            echo "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$safeFilename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function fetchStatus(SuratDinasRequest $request): RemoteMailStatus
    {
        return new RemoteMailStatus(
            publicId: (string) ($request->sisukma_public_id ?? Str::ulid()),
            status: 'registered',
            agendaNumber: $request->nomor_agenda,
            mailNumber: $request->nomor_surat,
        );
    }

    public function searchIncomingMails(string $query): array
    {
        return [
            [
                'public_id' => '01JTESTINCOMINGMAIL99999999',
                'agenda_number' => 'SM/'.now()->year.'/00001',
                'sender' => 'Ketua PTA Samarinda',
                'sender_agency' => 'Pengadilan Tinggi Agama Samarinda',
                'sender_mail_number' => 'W17-A/120/KP5.3/XI/2026',
                'mail_date' => now()->toDateString(),
                'received_date' => now()->toDateString(),
                'subject' => 'Surat Keputusan Izin Cuti'.($query !== '' ? " ({$query})" : ''),
                'status' => 'pending',
            ],
        ];
    }

    public function linkIncomingMail(
        string $publicId,
        string $externalReference,
        string $externalLabel,
        string $externalPath,
        string $linkedByNip,
    ): array {
        $entry = [
            'public_id' => $publicId,
            'agenda_number' => 'SM/'.now()->year.'/00001',
            'external_reference' => $externalReference,
            'external_label' => $externalLabel,
            'external_path' => $externalPath,
            'linked_by_nip' => $linkedByNip,
        ];
        $this->incomingLinks[] = $entry;

        return $entry;
    }

    public function streamIncomingMailFile(string $publicId, string $downloadFilename): StreamedResponse
    {
        $safeFilename = str_replace('"', '', basename($downloadFilename));

        return response()->stream(function (): void {
            echo "%PDF-1.4\n1 0 obj\n<< /Type /Catalog >>\nendobj\ntrailer\n<< /Root 1 0 R >>\n%%EOF\n";
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$safeFilename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
