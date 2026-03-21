<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware untuk verifikasi HMAC-SHA256 signature pada API requests.
 *
 * Mengamankan integritas request dengan:
 * 1. Verifikasi signature HMAC-SHA256
 * 2. Timestamp validation (anti-replay attack, window 5 menit)
 * 3. Query string tampering detection
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

        $secret = config('kepegawaian.secret_key');
        if (empty($secret)) {
            \Illuminate\Support\Facades\Log::critical('ATTENDANCE_HMAC_SECRET tidak dikonfigurasi');
            return response()->json(['message' => 'Service configuration error'], 500);
        }

        // Rekonstruksi payload: METHOD:PATH:SORTED_QUERY:BODY_SHA256:TIMESTAMP
        $queryString = http_build_query(collect($request->query())->sortKeys()->all());
        $bodyHash    = hash('sha256', $request->getContent());
        $payload     = strtoupper($request->method())
            .':'.$request->getPathInfo()
            .':'.$queryString
            .':'.$bodyHash
            .':'.$timestamp;

        $expected = hash_hmac('sha256', $payload, $secret);

        // Timing-safe comparison (mencegang timing attack)
        if (! hash_equals($expected, $received)) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        return $next($request);
    }
}
