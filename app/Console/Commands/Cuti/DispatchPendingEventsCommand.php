<?php

namespace App\Console\Commands\Cuti;

use App\Models\Cuti\CutiEventDelivery;
use App\Services\Cuti\ConsumerRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DispatchPendingEventsCommand extends Command
{
    protected $signature = 'cuti:dispatch-events {--limit=50 : Batas jumlah delivery yang diproses}';

    protected $description = 'Kirim event cuti yang pending ke consumer webhook';

    /** @var array<int, int> Backoff dalam detik per attempt */
    private const BACKOFF_SECONDS = [60, 300, 900, 3600, 21600, 86400];

    private const MAX_ATTEMPTS = 6;

    public function handle(ConsumerRegistry $registry): int
    {
        $limit = (int) $this->option('limit');

        $deliveries = CutiEventDelivery::query()
            ->with('event')
            ->where(function ($q) {
                $q->where('status', 'pending')
                    ->orWhere(function ($q2) {
                        $q2->where('status', 'failed')
                            ->where('next_retry_at', '<=', now());
                    });
            })
            ->where('attempts', '<', self::MAX_ATTEMPTS)
            ->orderBy('created_at')
            ->limit($limit)
            ->get();

        $delivered = 0;
        $failed = 0;

        foreach ($deliveries as $delivery) {
            try {
                $consumer = $registry->get($delivery->consumer_id);
            } catch (\InvalidArgumentException) {
                Log::warning("Consumer tidak ditemukan: {$delivery->consumer_id}");
                $delivery->update([
                    'status' => 'dead_letter',
                    'last_error' => "Consumer tidak terdaftar: {$delivery->consumer_id}",
                    'last_attempt_at' => now(),
                ]);

                continue;
            }

            $webhookUrl = $consumer['webhook_url'] ?? null;
            $secretEncrypted = $consumer['shared_secret_encrypted'] ?? null;

            if (! $webhookUrl || ! $secretEncrypted) {
                $delivery->update([
                    'status' => 'dead_letter',
                    'last_error' => 'Konfigurasi webhook_url atau shared_secret_encrypted kosong.',
                    'last_attempt_at' => now(),
                ]);

                continue;
            }

            $rawBody = json_encode($delivery->event->payload);
            $timestamp = now()->getTimestamp();
            $secret = Crypt::decryptString($secretEncrypted);

            // Bangun canonical string untuk signature
            $canonicalString = "{$delivery->event_id}.{$timestamp}.{$rawBody}";
            $signature = hash_hmac('sha256', $canonicalString, $secret);

            $delivery->update([
                'status' => 'in_flight',
                'last_attempt_at' => now(),
            ]);

            try {
                $response = Http::timeout(30)
                    ->withHeaders([
                        'X-Event-Id' => $delivery->event_id,
                        'X-Timestamp' => (string) $timestamp,
                        'X-Signature' => $signature,
                    ])
                    ->withBody($rawBody, 'application/json')
                    ->post($webhookUrl);

                if ($response->successful()) {
                    $delivery->update([
                        'status' => 'delivered',
                        'delivered_at' => now(),
                        'attempts' => $delivery->attempts + 1,
                    ]);
                    $delivered++;
                } else {
                    $this->markFailed($delivery, "HTTP {$response->status()}: {$response->body()}");
                    $failed++;
                }
            } catch (\Throwable $e) {
                $this->markFailed($delivery, $e->getMessage());
                $failed++;
            }
        }

        $this->info("Dispatch selesai: {$delivered} berhasil, {$failed} gagal dari {$deliveries->count()} delivery.");

        return self::SUCCESS;
    }

    /**
     * Tandai delivery sebagai gagal dengan exponential backoff.
     */
    private function markFailed(CutiEventDelivery $delivery, string $error): void
    {
        $newAttempts = $delivery->attempts + 1;

        if ($newAttempts >= self::MAX_ATTEMPTS) {
            $delivery->update([
                'status' => 'dead_letter',
                'attempts' => $newAttempts,
                'last_error' => $error,
            ]);

            return;
        }

        $backoffIndex = min($newAttempts - 1, count(self::BACKOFF_SECONDS) - 1);
        $backoffSeconds = self::BACKOFF_SECONDS[$backoffIndex];

        $delivery->update([
            'status' => 'failed',
            'attempts' => $newAttempts,
            'last_error' => $error,
            'next_retry_at' => now()->addSeconds($backoffSeconds),
        ]);
    }
}
