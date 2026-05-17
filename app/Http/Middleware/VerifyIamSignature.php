<?php

namespace App\Http\Middleware;

use App\Models\IamApplication;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use Symfony\Component\HttpFoundation\Response;

class VerifyIamSignature
{
    private const TIMESTAMP_WINDOW = 300;

    private const ACTIVITY_LOG_NAME = 'iam_audit';

    public function handle(Request $request, Closure $next): Response
    {
        $apiKey = $request->header('X-App-Key');
        $timestamp = $request->header('X-Timestamp');
        $received = $request->header('X-Signature');

        if (! $apiKey || ! $timestamp || ! $received) {
            $this->logFailure($request, null, 'missing_header', $timestamp);

            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        if (abs(now()->timestamp - (int) $timestamp) > self::TIMESTAMP_WINDOW) {
            $this->logFailure($request, null, 'invalid_timestamp', $timestamp);

            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        $app = IamApplication::where('api_key', $apiKey)->where('is_active', true)->first();

        if (! $app) {
            $this->logFailure($request, null, 'app_not_found', $timestamp);

            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Rekonstruksi payload: METHOD:PATH:SORTED_QUERY:BODY_SHA256:TIMESTAMP
        $queryString = http_build_query(collect($request->query())->sortKeys()->all());
        $bodyHash = hash('sha256', $request->getContent() ?? '');
        $payload = strtoupper($request->method())
            .':'.$request->getPathInfo()
            .':'.$queryString
            .':'.$bodyHash
            .':'.$timestamp;

        try {
            $secret = Crypt::decryptString($app->api_secret_hash);
            $expected = hash_hmac('sha256', $payload, $secret);

            if (! hash_equals($expected, $received)) {
                $this->logFailure($request, $app, 'signature_mismatch', $timestamp);

                return response()->json(['message' => 'Invalid credentials'], 401);
            }
        } catch (\Exception) {
            $this->logFailure($request, $app, 'signature_mismatch', $timestamp);

            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Inject app ke request attributes (aman, tidak bisa di-inject user)
        $request->attributes->set('iam_app', $app);

        return $next($request);
    }

    /**
     * Catat kegagalan verifikasi HMAC ke activity log untuk audit.
     */
    private function logFailure(Request $request, ?IamApplication $app, string $reason, ?string $receivedTimestamp): void
    {
        $properties = [
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'path' => $request->getPathInfo(),
            'method' => strtoupper($request->method()),
            'reason' => $reason,
            'received_timestamp' => $receivedTimestamp,
        ];

        $logger = activity(self::ACTIVITY_LOG_NAME);

        if ($app !== null) {
            $logger->performedOn($app);
        }

        $logger->event('hmac.verification_failed')
            ->withProperties($properties)
            ->log('hmac.verification_failed');
    }
}
