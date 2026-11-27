<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Memverifikasi signature HMAC-SHA256 webhook yang dikirim oleh SiSukma.
 *
 * Format kanonik: `"{event_id}.{timestamp}.{rawBody}"`.
 */
class VerifySisukmaWebhook
{
    /**
     * Memeriksa header webhook, jendela timestamp, dan signature HMAC-SHA256.
     *
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $eventId = $request->header('X-Event-Id');
        $timestamp = $request->header('X-Timestamp');
        $receivedSignature = $request->header('X-Signature');

        if (! is_string($eventId) || ! Str::isUuid($eventId)
            || ! is_string($timestamp) || ! ctype_digit($timestamp)
            || ! is_string($receivedSignature) || $receivedSignature === '') {
            return response()->json(['message' => 'Header webhook tidak valid.'], 401);
        }

        $window = max(1, (int) config('sisukma.timestamp_window_seconds', 300));
        if (abs(now()->timestamp - (int) $timestamp) > $window) {
            return response()->json(['message' => 'Timestamp webhook kedaluwarsa.'], 401);
        }

        $secret = (string) config('sisukma.webhook_secret', '');
        if ($secret === '') {
            Log::critical('SISUKMA_WEBHOOK_SECRET / SISUKMA_SHARED_SECRET belum dikonfigurasi.');

            return response()->json(['message' => 'Konfigurasi webhook belum lengkap.'], 500);
        }

        $canonical = "{$eventId}.{$timestamp}.".$request->getContent();
        $expectedSignature = hash_hmac('sha256', $canonical, $secret);

        if (! hash_equals($expectedSignature, $receivedSignature)) {
            return response()->json(['message' => 'Tanda tangan webhook tidak valid.'], 401);
        }

        return $next($request);
    }
}
