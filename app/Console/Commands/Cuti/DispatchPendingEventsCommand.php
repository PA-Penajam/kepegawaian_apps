<?php

namespace App\Console\Commands\Cuti;

use App\Models\Cuti\CutiEventDelivery;
use App\Services\Cuti\ConsumerRegistry;
use Illuminate\Console\Command;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class DispatchPendingEventsCommand extends Command
{
    protected $signature = 'cuti:dispatch-events {--limit=50 : Batas jumlah delivery yang diproses}';

    protected $description = 'Kirim event cuti yang pending ke consumer webhook';

    /** @var array<int, int> Backoff dalam detik per attempt */
    private const BACKOFF_SECONDS = [60, 300, 900, 3600, 21600, 86400];

    private const MAX_ATTEMPTS = 6;

    private const LEASE_TIMEOUT_SECONDS = 300;

    public function handle(ConsumerRegistry $registry): int
    {
        $limit = (int) $this->option('limit');
        $now = now();
        $leaseCutoff = $now->copy()->subSeconds(self::LEASE_TIMEOUT_SECONDS);

        // Klaim batch secara atomik di dalam transaksi agar tidak diproses ganda oleh worker paralel
        $deliveries = DB::transaction(function () use ($limit, $now, $leaseCutoff) {
            // Tutup delivery in_flight yang sudah mencapai batas percobaan dan mengalami lease timeout
            CutiEventDelivery::query()
                ->where('status', 'in_flight')
                ->where('attempts', '>=', self::MAX_ATTEMPTS)
                ->where(function ($q) use ($leaseCutoff): void {
                    $q->where('last_attempt_at', '<=', $leaseCutoff)
                        ->orWhere(function ($q2) use ($leaseCutoff): void {
                            $q2->whereNull('last_attempt_at')
                                ->where('updated_at', '<=', $leaseCutoff);
                        });
                })
                ->update([
                    'status' => 'dead_letter',
                    'last_error' => 'Melebihi batas maksimal percobaan akibat lease timeout berulang.',
                    'updated_at' => $now,
                ]);

            $claimed = CutiEventDelivery::query()
                ->with('event')
                ->where(function ($q) use ($now, $leaseCutoff): void {
                    $q->where('status', 'pending')
                        ->orWhere(function ($q2) use ($now): void {
                            $q2->where('status', 'failed')
                                ->where('next_retry_at', '<=', $now);
                        })
                        ->orWhere(function ($q3) use ($leaseCutoff): void {
                            $q3->where('status', 'in_flight')
                                ->where(function ($q4) use ($leaseCutoff): void {
                                    $q4->where('last_attempt_at', '<=', $leaseCutoff)
                                        ->orWhere(function ($q5) use ($leaseCutoff): void {
                                            $q5->whereNull('last_attempt_at')
                                                ->where('updated_at', '<=', $leaseCutoff);
                                        });
                                });
                        });
                })
                ->where('attempts', '<', self::MAX_ATTEMPTS)
                ->orderBy('created_at')
                ->limit($limit)
                ->lockForUpdate()
                ->get();

            if ($claimed->isNotEmpty()) {
                CutiEventDelivery::query()
                    ->whereIn('id', $claimed->pluck('id'))
                    ->update([
                        'status' => 'in_flight',
                        'attempts' => DB::raw('attempts + 1'),
                        'last_attempt_at' => $now,
                        'updated_at' => $now,
                    ]);

                $claimed->each(function (CutiEventDelivery $delivery) use ($now): void {
                    $delivery->status = 'in_flight';
                    $delivery->attempts = (int) $delivery->attempts + 1;
                    $delivery->last_attempt_at = $now;
                });
            }

            return $claimed;
        });

        $delivered = 0;
        $failed = 0;

        foreach ($deliveries as $delivery) {
            if ($delivery->event === null) {
                $delivery->update([
                    'status' => 'dead_letter',
                    'last_error' => 'Event relasi tidak ditemukan.',
                    'last_attempt_at' => now(),
                ]);
                $failed++;

                continue;
            }

            try {
                $consumer = $registry->get($delivery->consumer_id);
            } catch (\InvalidArgumentException) {
                Log::warning("Consumer tidak ditemukan: {$delivery->consumer_id}");
                $delivery->update([
                    'status' => 'dead_letter',
                    'last_error' => "Consumer tidak terdaftar: {$delivery->consumer_id}",
                    'last_attempt_at' => now(),
                ]);
                $failed++;

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
                $failed++;

                continue;
            }

            // Validasi URL anti-SSRF sebelum melakukan request keluar & dapatkan IP untuk pinning
            $validation = $this->resolveAndValidateWebhookUrl((string) $webhookUrl);
            if ($validation['error'] !== null) {
                $delivery->update([
                    'status' => 'dead_letter',
                    'last_error' => $validation['error'],
                    'last_attempt_at' => now(),
                ]);
                $failed++;

                continue;
            }

            try {
                $secret = Crypt::decryptString($secretEncrypted);
            } catch (\Throwable $e) {
                report($e);
                $this->markFailed($delivery, 'Gagal mendekripsi shared secret consumer.');
                $failed++;

                continue;
            }

            $rawBody = json_encode($delivery->event->payload);
            $timestamp = now()->getTimestamp();

            // Bangun canonical string untuk signature
            $canonicalString = "{$delivery->event_id}.{$timestamp}.{$rawBody}";
            $signature = hash_hmac('sha256', $canonicalString, $secret);

            $resolveEntries = $validation['host'] !== null && $validation['ips'] !== []
                ? $this->buildCurlResolveEntries($validation['host'], $validation['port'], $validation['ips'])
                : [];

            try {
                $response = $this->buildWebhookClient(
                    $delivery->event_id,
                    $timestamp,
                    $signature,
                    $rawBody,
                    $resolveEntries,
                )->post($webhookUrl);

                if ($response->successful()) {
                    $delivery->update([
                        'status' => 'delivered',
                        'delivered_at' => now(),
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
     * Validasi URL webhook terhadap potensi SSRF (wajib HTTPS, non-lokal, non-IP privat/reserved)
     * sekaligus mengembalikan daftar IP publik untuk DNS pinning.
     *
     * @return array{error: ?string, host: ?string, port: int, ips: array<int, string>}
     */
    protected function resolveAndValidateWebhookUrl(string $url): array
    {
        $allowPrivate = (bool) config('cuti.webhook.allow_private_hosts', false);

        $parts = parse_url($url);
        if ($parts === false) {
            return ['error' => 'URL webhook tidak valid.', 'host' => null, 'port' => 443, 'ips' => []];
        }

        $scheme = strtolower($parts['scheme'] ?? '');
        if ($allowPrivate) {
            if (! in_array($scheme, ['http', 'https'], true)) {
                return ['error' => 'URL webhook wajib menggunakan protokol HTTP atau HTTPS.', 'host' => null, 'port' => 443, 'ips' => []];
            }
        } elseif ($scheme !== 'https') {
            return ['error' => 'URL webhook wajib menggunakan protokol HTTPS.', 'host' => null, 'port' => 443, 'ips' => []];
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            return ['error' => 'URL webhook tidak boleh mengandung kredensial user/password.', 'host' => null, 'port' => 443, 'ips' => []];
        }

        $host = strtolower(trim($parts['host'] ?? '', '[]'));
        if ($host === '') {
            return ['error' => 'Host URL webhook tidak valid.', 'host' => null, 'port' => 443, 'ips' => []];
        }

        $port = isset($parts['port']) ? (int) $parts['port'] : ($scheme === 'http' ? 80 : 443);

        if (! $allowPrivate && (
            $host === 'localhost'
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal')
        )) {
            return ['error' => "Host URL webhook tidak diizinkan (lokal/internal): {$host}.", 'host' => $host, 'port' => $port, 'ips' => []];
        }

        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            if ($this->isLinkLocalOrMetadataIp($host)) {
                return ['error' => "Alamat IP metadata/link-local tidak diizinkan: {$host}.", 'host' => $host, 'port' => $port, 'ips' => []];
            }

            if (! $allowPrivate && ! $this->isPublicIp($host)) {
                return ['error' => "Alamat IP webhook tidak diizinkan (privat/reserved): {$host}.", 'host' => $host, 'port' => $port, 'ips' => []];
            }

            return ['error' => null, 'host' => $host, 'port' => $port, 'ips' => []];
        }

        if ($allowPrivate) {
            return ['error' => null, 'host' => $host, 'port' => $port, 'ips' => []];
        }

        $resolvedIps = $this->resolveHostIps($host);
        if ($resolvedIps === []) {
            if (app()->environment('testing') && $this->isHttpFakeActive()) {
                return ['error' => null, 'host' => $host, 'port' => $port, 'ips' => []];
            }

            return ['error' => "Gagal melakukan resolusi DNS untuk host webhook: {$host}.", 'host' => $host, 'port' => $port, 'ips' => []];
        }

        foreach ($resolvedIps as $ip) {
            if ($this->isLinkLocalOrMetadataIp($ip) || ! $this->isPublicIp($ip)) {
                return ['error' => "Host webhook mengarah ke alamat IP privat/reserved: {$ip}.", 'host' => $host, 'port' => $port, 'ips' => []];
            }
        }

        return ['error' => null, 'host' => $host, 'port' => $port, 'ips' => $resolvedIps];
    }

    /**
     * Memeriksa apakah IP berada di rentang link-local/cloud metadata (selalu diblokir).
     */
    private function isLinkLocalOrMetadataIp(string $ip): bool
    {
        $normalized = strtolower($ip);
        if (str_starts_with($normalized, '::ffff:')) {
            $mappedV4 = substr($normalized, 7);
            if (filter_var($mappedV4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                return $this->isLinkLocalOrMetadataIp($mappedV4);
            }
        }

        $long = ip2long($ip);
        if ($long !== false) {
            $start = ip2long('169.254.0.0');
            $end = ip2long('169.254.255.255');

            return $start !== false && $end !== false && $long >= $start && $long <= $end;
        }

        return str_starts_with($normalized, 'fe80:');
    }

    /**
     * Membangun daftar entri CURLOPT_RESOLVE untuk mengikat host ke IP publik tervalidasi.
     *
     * @param  array<int, string>  $resolvedIps
     * @return array<int, string>
     */
    protected function buildCurlResolveEntries(string $host, int $port, array $resolvedIps): array
    {
        if ($resolvedIps === []) {
            return [];
        }

        return [sprintf('%s:%d:%s', $host, $port, implode(',', $resolvedIps))];
    }

    /**
     * Membangun PendingRequest webhook dengan timeout, blokir redirect, header signature, dan IP pinning.
     *
     * @param  array<int, string>  $resolveEntries
     */
    protected function buildWebhookClient(
        string $eventId,
        int $timestamp,
        string $signature,
        string $rawBody,
        array $resolveEntries = [],
    ): PendingRequest {
        $client = Http::timeout(30)
            ->withoutRedirecting()
            ->withHeaders([
                'X-Event-Id' => $eventId,
                'X-Timestamp' => (string) $timestamp,
                'X-Signature' => $signature,
            ])
            ->withBody($rawBody, 'application/json');

        if ($resolveEntries !== [] && (! app()->environment('testing') || ! $this->isHttpFakeActive())) {
            $client = $client->withOptions([
                'curl' => [
                    CURLOPT_RESOLVE => $resolveEntries,
                ],
            ]);
        }

        return $client;
    }

    /**
     * Validasi URL webhook terhadap potensi SSRF (kompatibilitas helper).
     */
    private function validateWebhookUrl(string $url): ?string
    {
        return $this->resolveAndValidateWebhookUrl($url)['error'];
    }

    /**
     * Resolve daftar alamat IP A/AAAA dari nama host.
     *
     * @return array<int, string>
     */
    protected function resolveHostIps(string $host): array
    {
        $ips = [];

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if (is_array($records)) {
            foreach ($records as $record) {
                if (! empty($record['ip'])) {
                    $ips[] = $record['ip'];
                } elseif (! empty($record['ipv6'])) {
                    $ips[] = $record['ipv6'];
                }
            }
        }

        if ($ips === []) {
            $v4 = @gethostbynamel($host);
            if (is_array($v4)) {
                $ips = $v4;
            }
        }

        return array_values(array_unique($ips));
    }

    /**
     * Memastikan alamat IP merupakan IP publik (bukan loopback, privat, link-local, reserved, atau CGNAT).
     */
    private function isPublicIp(string $ip): bool
    {
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return false;
        }

        if (str_starts_with(strtolower($ip), '::ffff:')) {
            $mappedV4 = substr($ip, 7);
            if (filter_var($mappedV4, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
                return $this->isPublicIp($mappedV4);
            }
        }

        $long = ip2long($ip);
        if ($long !== false) {
            // Blokir CGNAT 100.64.0.0/10
            $cgnatStart = ip2long('100.64.0.0');
            $cgnatEnd = ip2long('100.127.255.255');
            if ($cgnatStart !== false && $cgnatEnd !== false && $long >= $cgnatStart && $long <= $cgnatEnd) {
                return false;
            }
        }

        return true;
    }

    /**
     * Mendeteksi apakah Http::fake() sedang aktif pada test runner.
     */
    private function isHttpFakeActive(): bool
    {
        $root = Http::getFacadeRoot();
        if (! is_object($root) || ! property_exists($root, 'stubCallbacks')) {
            return false;
        }

        $prop = new \ReflectionProperty($root, 'stubCallbacks');
        $callbacks = $prop->getValue($root);

        return $callbacks instanceof Collection && $callbacks->isNotEmpty();
    }

    /**
     * Tandai delivery sebagai gagal dengan exponential backoff.
     * Jumlah attempts sudah dinaikkan saat klaim batch pada awal siklus.
     */
    private function markFailed(CutiEventDelivery $delivery, string $error): void
    {
        $currentAttempts = (int) $delivery->attempts;

        if ($currentAttempts >= self::MAX_ATTEMPTS) {
            $delivery->update([
                'status' => 'dead_letter',
                'last_error' => $error,
            ]);

            return;
        }

        $backoffIndex = max(0, min($currentAttempts - 1, count(self::BACKOFF_SECONDS) - 1));
        $backoffSeconds = self::BACKOFF_SECONDS[$backoffIndex];

        $delivery->update([
            'status' => 'failed',
            'last_error' => $error,
            'next_retry_at' => now()->addSeconds($backoffSeconds),
        ]);
    }
}
