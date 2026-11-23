<?php

namespace App\Http\Middleware;

use App\Models\SyncConsumer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware untuk verifikasi HMAC-SHA256 signature pada API requests.
 *
 * Mengamankan integritas request dengan:
 * 1. Verifikasi status aktif konsumen (khusus SyncConsumer)
 * 2. Validasi header wajib X-Timestamp, X-Signature, dan X-Nonce (wajib untuk SyncConsumer)
 * 3. Timestamp validation (anti-replay attack, window 5 menit)
 * 4. Verifikasi signature HMAC-SHA256 + query string tampering detection
 * 5. Atomic replay protection pada X-Nonce (TTL 300 detik)
 */
class VerifyHmacSignature
{
    /**
     * Time window dalam detik untuk timestamp validation.
     * Request dengan timestamp lebih dari 5 menit yang lalu akan ditolak.
     */
    private const TIMESTAMP_WINDOW = 300;

    private const MAX_NONCE_LENGTH = 128;

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $isSyncConsumer = $user instanceof SyncConsumer;

        if ($isSyncConsumer && ! $user->is_active) {
            return response()->json(['message' => 'Konsumen sinkronisasi tidak aktif.'], 403);
        }

        $timestamp = $request->header('X-Timestamp');
        $received = $request->header('X-Signature');
        $rawNonce = $request->header('X-Nonce');
        $nonce = is_string($rawNonce) ? trim($rawNonce) : null;

        // Validasi header wajib ada
        if (! $timestamp || ! $received) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // SyncConsumer wajib mengirim X-Nonce unik
        if ($isSyncConsumer && ($nonce === null || $nonce === '' || mb_strlen($nonce) > self::MAX_NONCE_LENGTH)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Tolak request dengan timestamp > 5 menit (anti-replay)
        if (abs(now()->timestamp - (int) $timestamp) > self::TIMESTAMP_WINDOW) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $secret = $this->resolveSecret($request);

        if (empty($secret)) {
            if ($isSyncConsumer) {
                return response()->json(['message' => 'Invalid credentials'], 401);
            }

            Log::critical('HMAC secret global tidak dikonfigurasi');

            return response()->json(['message' => 'Service configuration error'], 500);
        }

        // Rekonstruksi payload: METHOD:PATH:SORTED_QUERY:BODY_SHA256:TIMESTAMP
        $queryString = http_build_query(collect($request->query())->sortKeys()->all());
        $bodyHash = hash('sha256', $request->getContent());
        $payload = strtoupper($request->method())
            .':'.$request->getPathInfo()
            .':'.$queryString
            .':'.$bodyHash
            .':'.$timestamp;

        $expected = hash_hmac('sha256', $payload, $secret);

        // Timing-safe comparison (mencegah timing attack)
        if (! hash_equals($expected, $received)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if ($isSyncConsumer) {
            $nonceKey = "hmac_nonce:consumer:{$user->id}:".hash('sha256', (string) $nonce);

            if (! Cache::add($nonceKey, true, self::TIMESTAMP_WINDOW)) {
                return response()->json(['message' => 'Invalid credentials'], 401);
            }
        }

        return $next($request);
    }

    /**
     * Ambil secret yang berlaku untuk request ini.
     * SyncConsumer wajib memakai hmac_secret terenkripsi miliknya sendiri tanpa fallback global.
     */
    private function resolveSecret(Request $request): ?string
    {
        $user = $request->user();

        if ($user instanceof SyncConsumer) {
            try {
                $consumerSecret = $user->hmac_secret;

                return is_string($consumerSecret) && $consumerSecret !== '' ? $consumerSecret : null;
            } catch (\Throwable $e) {
                Log::warning('Gagal mendekripsi HMAC secret saat verifikasi', [
                    'consumer_slug' => $user->slug ?? null,
                    'exception' => $e->getMessage(),
                ]);

                return null;
            }
        }

        $global = config('kepegawaian.secret_key');

        return is_string($global) && $global !== '' ? $global : null;
    }
}
