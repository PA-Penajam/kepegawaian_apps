<?php

namespace App\Services\Sync;

use App\Models\SyncConsumer;
use Illuminate\Support\Facades\Http;

/**
 * Penguji koneksi konsumen sinkronisasi.
 *
 * Menyimulasikan persis apa yang dilakukan aplikasi client: membangun
 * HMAC-SHA256 signature dari string kanonik memakai HMAC secret
 * per konsumen (fallback global untuk konsumen lawas), lalu melakukan
 * GET ke endpoint /api/v1/pegawai/sync.
 *
 * Keamanan:
 * - Plaintext token asli tidak tersimpan (hanya hash), sehingga uji
 *   memakai token sementara berumur 5 menit yang langsung dihapus
 *   seusai pemanggilan. Token asli konsumen tidak tersentuh.
 * - Token dan secret tidak pernah di-log.
 */
class SyncConnectionTester
{
    /**
     * Jalankan uji koneksi end-to-end terhadap endpoint sync sendiri.
     *
     * @return array{success: bool, message: string}
     */
    public function test(SyncConsumer $consumer): array
    {
        $token = $consumer->tokens()->latest('id')->first();

        if ($token === null) {
            return [
                'success' => false,
                'message' => 'Konsumen belum memiliki token API. Terbitkan token terlebih dahulu.',
            ];
        }

        // Cek apakah token masih berlaku
        if ($token->expires_at !== null && $token->expires_at->isPast()) {
            return [
                'success' => false,
                'message' => 'Token API telah kedaluwarsa. Regenerasi token untuk membuat yang baru.',
            ];
        }

        $secret = $this->resolveSecret($consumer);

        if ($secret === null) {
            return [
                'success' => false,
                'message' => 'Konsumen belum memiliki HMAC secret dan secret global kosong. Regenerasi secret konsumen terlebih dahulu.',
            ];
        }

        // Token sementara agar request benar-benar terautentikasi tanpa
        // menyentuh token asli konsumen.
        $ephemeral = $consumer->createToken('sync-test', ['app:kepegawaian'], now()->addMinutes(5));
        $plaintext = $ephemeral->plainTextToken;

        try {
            // Bangun request bertanda tangan terhadap endpoint sync
            $path = '/api/v1/pegawai/sync';
            $query = ['page' => 1, 'per_page' => 1];
            $timestamp = now()->timestamp;

            $signedQuery = collect($query)->sortKeys()->all();
            $queryString = http_build_query($signedQuery);
            $payload = 'GET:'.$path.':'.$queryString.':'.hash('sha256', '').':'.$timestamp;
            $signature = hash_hmac('sha256', $payload, $secret);

            $response = Http::timeout(10)
                ->withToken($plaintext)
                ->withHeaders([
                    'X-Timestamp' => (string) $timestamp,
                    'X-Signature' => $signature,
                    'Accept' => 'application/json',
                ])
                ->get($this->appUrl().$path.'?'.$queryString);

            if ($response->successful()) {
                return [
                    'success' => true,
                    'message' => 'Koneksi berhasil: endpoint sync menjawab HTTP '.$response->status().'.',
                ];
            }

            return [
                'success' => false,
                'message' => 'Endpoint sync menjawab HTTP '.$response->status().' — periksa token, HMAC secret, dan konfigurasi URL aplikasi.',
            ];
        } finally {
            $ephemeral->accessToken->delete();
        }
    }

    /**
     * Secret per konsumen bila sudah ada, selain itu secret global.
     */
    private function resolveSecret(SyncConsumer $consumer): ?string
    {
        try {
            $consumerSecret = $consumer->hmac_secret;

            if (is_string($consumerSecret) && $consumerSecret !== '') {
                return $consumerSecret;
            }
        } catch (\Throwable) {
            // Lanjut ke fallback global
        }

        $global = config('kepegawaian.secret_key');

        return is_string($global) && $global !== '' ? $global : null;
    }

    private function appUrl(): string
    {
        return rtrim((string) config('app.url'), '/');
    }
}
