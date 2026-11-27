<?php

namespace App\Services\SuratDinas;

use App\Models\SuratDinas\SuratDinasRequest;
use App\Services\Sisukma\Exceptions\SisukmaException;
use App\Services\Sisukma\SisukmaClient;
use App\Services\SuratDinas\Contracts\PenomoranSuratGateway;
use App\Services\SuratDinas\DTO\RegistrationResult;
use App\Services\SuratDinas\DTO\RemoteMailStatus;
use Illuminate\Support\Facades\File;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SisukmaPenomoranGateway implements PenomoranSuratGateway
{
    public function __construct(
        private readonly SisukmaClient $client,
    ) {}

    public function register(SuratDinasRequest $request): RegistrationResult
    {
        $response = $this->client->post('/api/integrations/v1/outgoing-mails', [
            'external_reference' => $request->id,
            'external_path' => $request->external_path,
            'classification_code' => $request->klasifikasi,
            'signer' => $request->penandatangan,
            'document_type' => $request->document_type,
            'subject' => $request->perihal,
            'recipient_name' => $request->tujuan_nama,
            'recipient_agency' => $request->tujuan_instansi ?? 'Pengadilan Agama Penajam',
            'recipient_address' => $request->tujuan_alamat,
            'mail_date' => now()->toDateString(),
            'requested_by_nip' => $request->requested_by_nip,
        ]);

        $data = (array) ($response['data'] ?? []);

        return new RegistrationResult(
            publicId: (string) ($data['public_id'] ?? ''),
            agendaNumber: (string) ($data['agenda_number'] ?? ''),
            status: (string) ($data['status'] ?? 'registered'),
        );
    }

    public function resubmit(SuratDinasRequest $request): void
    {
        $publicId = $this->requirePublicId($request);

        $this->client->post("/api/integrations/v1/outgoing-mails/{$publicId}/resubmit", [
            'classification_code' => $request->klasifikasi,
            'signer' => $request->penandatangan,
            'document_type' => $request->document_type,
            'subject' => $request->perihal,
            'recipient_name' => $request->tujuan_nama,
            'recipient_agency' => $request->tujuan_instansi ?? 'Pengadilan Agama Penajam',
            'recipient_address' => $request->tujuan_alamat,
            'requested_by_nip' => $request->requested_by_nip,
        ]);
    }

    public function cancel(SuratDinasRequest $request, string $reason): void
    {
        $publicId = $this->requirePublicId($request);

        $this->client->post("/api/integrations/v1/outgoing-mails/{$publicId}/cancel", [
            'reason' => $reason,
            'requested_by_nip' => $request->requested_by_nip,
        ]);
    }

    public function replaceDraft(SuratDinasRequest $request, string $absolutePath): void
    {
        $publicId = $this->requirePublicId($request);

        if (! File::exists($absolutePath)) {
            throw new SisukmaException("Berkas draf PDF tidak ditemukan di {$absolutePath}.");
        }

        $rawPdf = File::get($absolutePath);
        $sha256 = hash('sha256', $rawPdf);

        $this->client->putRaw(
            path: "/api/integrations/v1/outgoing-mails/{$publicId}/draft",
            rawBody: $rawPdf,
            contentType: 'application/pdf',
            extraHeaders: [
                'X-Content-Sha256' => $sha256,
                'X-Requested-By-Nip' => $request->requested_by_nip,
            ],
        );
    }

    public function streamFinal(SuratDinasRequest $request, string $downloadFilename): StreamedResponse
    {
        $publicId = $this->requirePublicId($request);
        $remote = $this->client->stream("/api/integrations/v1/outgoing-mails/{$publicId}/letter");
        $body = $remote->toPsrResponse()->getBody();
        $safeFilename = str_replace('"', '', basename($downloadFilename));

        return response()->stream(function () use ($body): void {
            while (! $body->eof()) {
                echo $body->read(65536);
            }
        }, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$safeFilename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function fetchStatus(SuratDinasRequest $request): RemoteMailStatus
    {
        $publicId = $this->requirePublicId($request);
        $response = $this->client->get("/api/integrations/v1/outgoing-mails/{$publicId}");
        $data = (array) ($response['data'] ?? []);

        return new RemoteMailStatus(
            publicId: (string) ($data['public_id'] ?? $publicId),
            status: (string) ($data['status'] ?? ''),
            agendaNumber: $data['agenda_number'] ?? null,
            mailNumber: $data['mail_number'] ?? null,
            revisionNote: $data['revision_note'] ?? null,
            voidReason: $data['void_reason'] ?? null,
            hasDraft: (bool) ($data['has_draft'] ?? false),
            hasLetter: (bool) ($data['has_letter'] ?? false),
        );
    }

    public function searchIncomingMails(string $query): array
    {
        $response = $this->client->get('/api/integrations/v1/incoming-mails', [
            'q' => $query,
        ]);

        return array_values((array) ($response['data'] ?? []));
    }

    public function linkIncomingMail(
        string $publicId,
        string $externalReference,
        string $externalLabel,
        string $externalPath,
        string $linkedByNip,
    ): array {
        $response = $this->client->post("/api/integrations/v1/incoming-mails/{$publicId}/links", [
            'external_reference' => $externalReference,
            'external_label' => $externalLabel,
            'external_path' => $externalPath,
            'linked_by_nip' => $linkedByNip,
        ]);

        return (array) ($response['data'] ?? []);
    }

    public function streamIncomingMailFile(string $publicId, string $downloadFilename): StreamedResponse
    {
        $remote = $this->client->stream("/api/integrations/v1/incoming-mails/{$publicId}/file");
        $body = $remote->toPsrResponse()->getBody();
        $contentType = $remote->header('Content-Type') ?: 'application/pdf';
        $safeFilename = str_replace('"', '', basename($downloadFilename));

        return response()->stream(function () use ($body): void {
            while (! $body->eof()) {
                echo $body->read(65536);
            }
        }, 200, [
            'Content-Type' => $contentType,
            'Content-Disposition' => 'inline; filename="'.$safeFilename.'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Memastikan `sisukma_public_id` tersedia sebelum memanggil endpoint spesifik surat.
     */
    private function requirePublicId(SuratDinasRequest $request): string
    {
        if (blank($request->sisukma_public_id)) {
            throw new SisukmaException("Surat dinas [{$request->id}] belum memiliki public_id dari SiSukma.");
        }

        return (string) $request->sisukma_public_id;
    }
}
