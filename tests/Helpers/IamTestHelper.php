<?php

use App\Models\IamApplication;
use Illuminate\Support\Facades\Crypt;

if (! function_exists('makeIamHeaders')) {
    /**
     * Generate valid IAM signature headers untuk testing.
     * Format payload: METHOD:PATH:SORTED_QUERY:BODY_SHA256:TIMESTAMP
     *
     * Overload 1: makeIamHeaders(string $method, string $path, array $body = [], array $query = []): array
     *   - Returns: [$app, $headers]
     *
     * Overload 2: makeIamHeaders(string $method, string $path, string $apiKey, string $apiSecret, array $query = []): array
     *   - Returns: $headers only (backward compatibility)
     *
     * @param  array<string, mixed>|string  $arg3  Body array OR apiKey string
     * @param  array<string, mixed>|string  $arg4  Query array OR apiSecret string
     * @return array{0: IamApplication, 1: array<string, string>}|array<string, string>
     */
    function makeIamHeaders(string $method, string $path, array|string $arg3 = [], array|string $arg4 = [], array $arg5 = []): array
    {
        // Detect signature: if arg3 is string, it's apiKey (old signature)
        if (is_string($arg3)) {
            // Old signature: makeIamHeaders($method, $path, $apiKey, $apiSecret, $query = [])
            $apiKey = $arg3;
            $apiSecret = is_string($arg4) ? $arg4 : '';
            $query = $arg5;
            $timestamp = now()->timestamp;
            $queryString = http_build_query(collect($query)->sortKeys()->all());
            // Old format without body hash for backward compatibility
            $payload = strtoupper($method) . ':' . $path . ':' . $queryString . ':' . $timestamp;
            $signature = hash_hmac('sha256', $payload, $apiSecret);

            return [
                'X-App-Key'   => $apiKey,
                'X-Signature' => $signature,
                'X-Timestamp' => $timestamp,
                'Accept'      => 'application/json',
            ];
        }

        // New signature: makeIamHeaders($method, $path, $body = [], $query = [])
        $body = is_array($arg3) ? $arg3 : [];
        $query = is_array($arg4) ? $arg4 : [];

        $app      = IamApplication::factory()->create(['is_active' => true]);
        $secret   = Crypt::decryptString($app->api_secret_hash);
        $ts       = now()->timestamp;
        $qs       = http_build_query(collect($query)->sortKeys()->all());
        $bodyHash = hash('sha256', $body ? json_encode($body) : '');
        $payload  = strtoupper($method) . ':' . $path . ':' . $qs . ':' . $bodyHash . ':' . $ts;
        $sig      = hash_hmac('sha256', $payload, $secret);

        return [$app, [
            'X-App-Key'   => $app->api_key,
            'X-Timestamp' => $ts,
            'X-Signature' => $sig,
        ]];
    }
}
