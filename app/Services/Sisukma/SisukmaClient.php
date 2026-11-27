<?php

namespace App\Services\Sisukma;

use App\Services\Sisukma\Exceptions\SisukmaException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Request;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Psr\Http\Message\RequestInterface;

/**
 * Klien HTTP bertanda tangan HMAC-SHA256 untuk berkomunikasi dengan SiSukma.
 */
class SisukmaClient
{
    /**
     * Memeriksa konektivitas dan validitas kredensial ke SiSukma.
     *
     * @return array<string, mixed>
     */
    public function ping(): array
    {
        return $this->get('/api/integrations/v1/ping');
    }

    /**
     * Mengirim permintaan GET bertanda tangan HMAC dan mengembalikan body JSON.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    public function get(string $path, array $query = []): array
    {
        return $this->executeJson('GET', $path, $query);
    }

    /**
     * Mengirim permintaan POST JSON bertanda tangan HMAC dan mengembalikan body JSON.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public function post(string $path, array $data = []): array
    {
        return $this->executeJson('POST', $path, $data);
    }

    /**
     * Mengirim permintaan PUT dengan payload biner mentah (mis. berkas PDF).
     *
     * @param  array<string, string>  $extraHeaders
     * @return array<string, mixed>
     */
    public function putRaw(string $path, string $rawBody, string $contentType = 'application/pdf', array $extraHeaders = []): array
    {
        $config = $this->validatedConfig();
        $normalizedPath = '/'.ltrim($path, '/');

        try {
            $response = $this->buildSignedRequest($config)
                ->withHeaders($extraHeaders)
                ->withBody($rawBody, $contentType)
                ->put($config['base_url'].$normalizedPath);

            $response->throw();

            return (array) $response->json();
        } catch (RequestException $e) {
            $this->throwRequestException($normalizedPath, $e);
        } catch (\Throwable $e) {
            if ($e instanceof SisukmaException) {
                throw $e;
            }

            throw new SisukmaException('Gagal menghubungi SiSukma: '.$e->getMessage(), null, $e);
        }
    }

    /**
     * Mengambil respons stream untuk keperluan proxy unduhan berkas.
     */
    public function stream(string $path): Response
    {
        $config = $this->validatedConfig();
        $normalizedPath = '/'.ltrim($path, '/');

        try {
            $response = $this->buildSignedRequest($config, acceptJson: false)
                ->withOptions(['stream' => true])
                ->get($config['base_url'].$normalizedPath);

            $response->throw();

            return $response;
        } catch (RequestException $e) {
            $this->throwRequestException($normalizedPath, $e);
        } catch (\Throwable $e) {
            if ($e instanceof SisukmaException) {
                throw $e;
            }

            throw new SisukmaException('Gagal mengunduh berkas dari SiSukma: '.$e->getMessage(), null, $e);
        }
    }

    /**
     * Menjalankan permintaan JSON (GET atau POST) dengan retry otomatis pada kesalahan transien.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function executeJson(string $method, string $path, array $payload): array
    {
        $config = $this->validatedConfig();
        $normalizedPath = '/'.ltrim($path, '/');

        try {
            $pending = $this->buildSignedRequest($config);
            $url = $config['base_url'].$normalizedPath;

            $response = $method === 'GET'
                ? $pending->get($url, $payload)
                : $pending->post($url, $payload);

            $response->throw();

            return (array) $response->json();
        } catch (RequestException $e) {
            $this->throwRequestException($normalizedPath, $e);
        } catch (\Throwable $e) {
            if ($e instanceof SisukmaException) {
                throw $e;
            }

            throw new SisukmaException('Gagal menghubungi SiSukma: '.$e->getMessage(), null, $e);
        }
    }

    /**
     * Membangun PendingRequest yang menandatangani ulang setiap attempt dengan nonce baru.
     *
     * @param  array{base_url: string, client_id: string, secret: string, timeout: int}  $config
     */
    private function buildSignedRequest(array $config, bool $acceptJson = true): PendingRequest
    {
        $request = Http::timeout($config['timeout']);

        if ($acceptJson) {
            $request = $request->acceptJson();
        }

        return $request
            ->beforeSending(function (Request $httpRequest) use ($config): RequestInterface {
                $parts = parse_url($httpRequest->url());
                $path = $parts['path'] ?? '/';
                $queryArray = [];
                if (isset($parts['query']) && $parts['query'] !== '') {
                    parse_str($parts['query'], $queryArray);
                }
                ksort($queryArray);
                $queryString = http_build_query($queryArray);

                $timestamp = (string) time();
                $nonce = (string) Str::uuid();
                $bodyHash = hash('sha256', $httpRequest->body());
                $canonical = strtoupper($httpRequest->method())
                    .':'.$path
                    .':'.$queryString
                    .':'.$bodyHash
                    .':'.$timestamp;
                $signature = hash_hmac('sha256', $canonical, $config['secret']);

                return $httpRequest->toPsrRequest()
                    ->withHeader('X-Client-Id', $config['client_id'])
                    ->withHeader('X-Timestamp', $timestamp)
                    ->withHeader('X-Nonce', $nonce)
                    ->withHeader('X-Signature', $signature);
            })
            ->retry(3, 500, static function (\Throwable $exception): bool {
                if ($exception instanceof ConnectionException) {
                    return true;
                }

                if ($exception instanceof RequestException) {
                    $status = $exception->response->status();

                    return $status === 429 || $status >= 500;
                }

                return false;
            });
    }

    /**
     * Memvalidasi konfigurasi wajib SiSukma sebelum melakukan request keluar.
     *
     * @return array{base_url: string, client_id: string, secret: string, timeout: int}
     */
    private function validatedConfig(): array
    {
        $baseUrl = rtrim((string) config('sisukma.base_url', ''), '/');
        $clientId = trim((string) config('sisukma.client_id', ''));
        $secret = (string) config('sisukma.secret', '');
        $timeout = max(1, (int) config('sisukma.timeout', 10));

        $missing = [];
        if ($baseUrl === '') {
            $missing[] = 'SISUKMA_BASE_URL';
        }
        if ($clientId === '') {
            $missing[] = 'SISUKMA_CLIENT_ID';
        }
        if ($secret === '') {
            $missing[] = 'SISUKMA_SHARED_SECRET';
        }

        if ($missing !== []) {
            throw new SisukmaException(
                'Konfigurasi integrasi SiSukma belum lengkap: '.implode(', ', $missing).'.',
            );
        }

        return [
            'base_url' => $baseUrl,
            'client_id' => $clientId,
            'secret' => $secret,
            'timeout' => $timeout,
        ];
    }

    /**
     * Membungkus kegagalan respons HTTP SiSukma ke SisukmaException dengan logging aman.
     */
    private function throwRequestException(string $path, RequestException $e): never
    {
        $status = $e->response?->status();

        Log::warning('Permintaan ke SiSukma gagal.', [
            'path' => $path,
            'status' => $status,
            'error' => $e->getMessage(),
        ]);

        throw new SisukmaException(
            'Gagal memproses permintaan ke SiSukma (HTTP '.($status ?? 0).').',
            $status,
            $e,
        );
    }
}
