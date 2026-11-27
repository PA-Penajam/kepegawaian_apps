<?php

namespace App\Services\Sisukma\Handlers;

use App\Models\Integration\IntegrationInboundEvent;
use App\Models\SuratDinas\SuratDinasRequest;
use App\Services\Sisukma\Contracts\SisukmaInboundHandler;
use App\Services\SuratDinas\SuratDinasRequestService;

/**
 * Menangani event perubahan status surat keluar dari SiSukma.
 */
class OutgoingMailWebhookHandler implements SisukmaInboundHandler
{
    /** @var list<string> */
    private const SUPPORTED_EVENTS = [
        'outgoing_mail.number_issued',
        'outgoing_mail.revision_requested',
        'outgoing_mail.voided',
        'outgoing_mail.letter_accepted',
        'outgoing_mail.sent',
    ];

    public function __construct(
        private readonly SuratDinasRequestService $suratDinasService,
    ) {}

    public function supports(string $eventType): bool
    {
        return in_array($eventType, self::SUPPORTED_EVENTS, true);
    }

    public function handle(IntegrationInboundEvent $event): void
    {
        $data = (array) ($event->payload['data'] ?? []);
        $externalRef = $data['external_reference'] ?? null;
        $publicId = $data['public_id'] ?? null;

        $request = SuratDinasRequest::query()
            ->when(is_string($externalRef) && $externalRef !== '', fn ($q) => $q->where('id', $externalRef))
            ->when((! is_string($externalRef) || $externalRef === '') && is_string($publicId) && $publicId !== '', fn ($q) => $q->where('sisukma_public_id', $publicId))
            ->first();

        if ($request === null) {
            return;
        }

        match ($event->event_type) {
            'outgoing_mail.number_issued' => $this->handleNumberIssued($request, $data),
            'outgoing_mail.revision_requested' => $this->suratDinasService->applyRevisionRequested(
                $request,
                (string) ($data['revision_note'] ?? 'Perlu perbaikan di SiSukma.'),
            ),
            'outgoing_mail.voided' => $this->suratDinasService->applyVoided(
                $request,
                (string) ($data['void_reason'] ?? 'Dibatalkan oleh Admin TU SiSukma.'),
            ),
            'outgoing_mail.letter_accepted' => $this->suratDinasService->applyLetterAccepted(
                $request,
                isset($data['file_checksum']) ? (string) $data['file_checksum'] : null,
                isset($data['file_size_bytes']) ? (int) $data['file_size_bytes'] : null,
            ),
            'outgoing_mail.sent' => $this->suratDinasService->applySent($request),
            default => null,
        };
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function handleNumberIssued(SuratDinasRequest $request, array $data): void
    {
        $mailNumber = (string) ($data['mail_number'] ?? '');
        if ($mailNumber === '') {
            return;
        }

        $agendaNumber = isset($data['agenda_number']) ? (string) $data['agenda_number'] : null;
        $this->suratDinasService->applyNumberIssued($request, $mailNumber, $agendaNumber);
    }
}
