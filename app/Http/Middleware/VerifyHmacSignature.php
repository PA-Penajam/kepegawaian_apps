<?php

namespace App\Http\Middleware;

use App\Models\SyncConsumer;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware untuk verifikasi HMAC-SHA256 signature pada API requests.
 *
 * Mengamankan integritas request dengan:
 * 1. Verifikasi signature HMAC-SHA256
 * 2. Timestamp validation (anti-replay attack, window 5 menit)
 * 3. Query string tampering detection
 *
 * Resolusi secret (1 client 1 secret): bila pemanggil terautentikasi
 * sebagai SyncConsumer yang sudah memiliki hmac_secret per konsumen,
 * secret itu yang dipakai. Selain itu (konsumen lawas maupun integrasi
 * non-sync) fallback ke secret global config kepegawaian.secret_key
 * agar masa transisi tidak memutus client lama.
 */
class VerifyHmacSignature
{
    /**
     * Time window dalam detik untuk timestamp validation.
     * Request dengan timestamp lebih dari 5 menit yang lalu akan ditolak.
     */
    private const TIMESTAMP_WINDOW = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $timestamp = $request->header('X-Timestamp');
        $received = $request->header('X-Signature');

        // Validasi header wajib ada
        if (! $timestamp || ! $received) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Tolak request dengan timestamp > 5 menit (anti-replay)
        if (abs(now()->timestamp - (int) $timestamp) > self::TIMESTAMP_WINDOW) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $secret = $this->resolveSecret($request);

        if (empty($secret)) {
            Log::critical('HMAC secret tidak dikonfigurasi (per konsumen maupun global)');

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

        return $next($request);
    }

    /**
     * Ambil secret yang berlaku untuk request ini: secret per konsumen
     * bila pemanggil adalah SyncConsumer yang sudah memilikinya,
     * selain itu secret global sebagai fallback masa transisi.
     */
    private function resolveSecret(Request $request): ?string
    {
        $user = $request->user();

        if ($user instanceof SyncConsumer) {
            try {
                $consumerSecret = $user->hmac_secret;

                if (is_string($consumerSecret) && $consumerSecret !== '') {
                    return $consumerSecret;
                }
            } catch (\Throwable $e) {
                Log::warning('Gagal mendekripsi HMAC secret saat verifikasi', [
                    'consumer_slug' => $user->slug ?? null,
                    'exception' => $e->getMessage(),
                ]);
            }
        }

        $global = config('kepegawaian.secret_key');

        return is_string($global) && $global !== '' ? $global : null;
    }
}
