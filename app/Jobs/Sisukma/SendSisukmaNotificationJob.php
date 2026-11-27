<?php

namespace App\Jobs\Sisukma;

use App\Services\Sisukma\SisukmaClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Mengirim notifikasi aplikasi ke SiSukma (lonceng & Telegram) secara asinkron.
 */
class SendSisukmaNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /**
     * @var list<int>
     */
    public array $backoff = [10, 60, 300];

    /**
     * @param  array<string, string|int|null>  $metadata
     */
    public function __construct(
        public readonly string $recipientNip,
        public readonly string $idempotencyKey,
        public readonly string $title,
        public readonly string $message,
        public readonly string $actionPath,
        public readonly string $type = 'cuti',
        public readonly array $metadata = [],
    ) {}

    public function handle(SisukmaClient $client): void
    {
        if (! (bool) config('sisukma.enabled') || ! (bool) config('sisukma.notifications_enabled')) {
            return;
        }

        $baseUrl = rtrim((string) config('sisukma.app_url', config('app.url', 'http://localhost')), '/');
        $path = '/'.ltrim($this->actionPath, '/');

        $client->post('/api/integrations/v1/notifications', [
            'idempotency_key' => $this->idempotencyKey,
            'recipient_nip' => $this->recipientNip,
            'title' => $this->title,
            'message' => $this->message,
            'action_url' => $baseUrl.$path,
            'type' => $this->type,
            'metadata' => $this->metadata,
        ]);
    }
}
