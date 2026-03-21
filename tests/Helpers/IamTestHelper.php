<?php

use App\Models\IamApplication;
use Illuminate\Support\Facades\Crypt;

if (! function_exists('makeIamHeaders')) {
    /**
     * Generate valid IAM signature headers untuk testing.
     * Format payload: METHOD:PATH:SORTED_QUERY:BODY_SHA256:TIMESTAMP
     *
     * @param  string  $method  HTTP method
     * @param  string  $path  Request path
     * @param  array<string, mixed>  $body  Request body
     * @param  array<string, mixed>  $query  Query params
     * @return array{0: IamApplication, 1: array<string, string>}
     */
    function makeIamHeaders(string $method, string $path, array $body = [], array $query = []): array
    {
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
