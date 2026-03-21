<?php

namespace App\Http\Middleware;

use App\Models\IamApplication;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyIamSignature
{
    private const TIMESTAMP_WINDOW = 300;

    public function handle(Request $request, Closure $next): Response
    {
        $apiKey    = $request->header('X-App-Key');
        $timestamp = $request->header('X-Timestamp');
        $received  = $request->header('X-Signature');

        if (! $apiKey || ! $timestamp || ! $received) {
            return response()->json(['message' => 'Missing IAM signature headers'], 401);
        }

        if (abs(now()->timestamp - (int) $timestamp) > self::TIMESTAMP_WINDOW) {
            return response()->json(['message' => 'Request expired'], 401);
        }

        $app = IamApplication::where('api_key', $apiKey)->where('is_active', true)->first();

        if (! $app) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Coba verifikasi dengan format baru (dengan body hash) terlebih dahulu
        $queryString = http_build_query(collect($request->query())->sortKeys()->all());
        $bodyHash    = hash('sha256', $request->getContent());
        $payloadNew  = strtoupper($request->method())
            . ':' . $request->getPathInfo()
            . ':' . $queryString
            . ':' . $bodyHash
            . ':' . $timestamp;

        // Coba juga format lama (tanpa body hash) untuk backward compatibility
        $payloadOld = strtoupper($request->method())
            . ':' . $request->getPathInfo()
            . ':' . $queryString
            . ':' . $timestamp;

        try {
            $secret = \Illuminate\Support\Facades\Crypt::decryptString($app->api_secret_hash);

            $expectedNew = hash_hmac('sha256', $payloadNew, $secret);
            $expectedOld = hash_hmac('sha256', $payloadOld, $secret);

            // Verifikasi dengan salah satu format (new atau old)
            if (! hash_equals($expectedNew, $received) && ! hash_equals($expectedOld, $received)) {
                return response()->json(['message' => 'Invalid credentials'], 401);
            }
        } catch (\Exception) {
            return response()->json(['message' => 'Invalid credentials'], 401);
        }

        // Inject app ke request attributes (aman, tidak bisa di-inject user via query string)
        $request->attributes->set('iam_app', $app);

        return $next($request);
    }
}
