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
            return response()->json(['message' => 'Unknown application'], 401);
        }

        // Rekonstruksi payload: METHOD:PATH:SORTED_QUERY:TIMESTAMP
        $queryString = http_build_query(collect($request->query())->sortKeys()->all());
        $payload     = strtoupper($request->method())
            . ':' . $request->getPathInfo()
            . ':' . $queryString
            . ':' . $timestamp;

        try {
            $secret   = \Illuminate\Support\Facades\Crypt::decryptString($app->api_secret_hash);
            $expected = hash_hmac('sha256', $payload, $secret);

            if (! hash_equals($expected, $received)) {
                return response()->json(['message' => 'Invalid signature'], 401);
            }
        } catch (\Exception) {
            return response()->json(['message' => 'Invalid signature'], 401);
        }

        // Inject app ke request untuk digunakan controller
        $request->merge(['_iam_app' => $app]);

        return $next($request);
    }
}
