<?php

namespace App\Services\Iam;

use App\Models\SyncConsumer;
use Illuminate\Support\Facades\Log;

/**
 * Pengelola kredensial API konsumen sinkronisasi pegawai.
 *
 * Setiap konsumen memiliki DUA kredensial unik (1 client 1 secret):
 * - Sanctum token bernama "sync:{slug}" dengan ability "app:kepegawaian".
 *   Plaintext token hanya dikembalikan sekali saat penerbitan/regenerasi.
 * - HMAC secret per konsumen untuk tanda tangan X-Signature. Disimpan
 *   terenkripsi pada model dan plaintext hanya dikembalikan sekali saat
 *   penerbitan/regenerasi secret.
 */
class SyncConsumerCredentialService
{
    private const ACTIVITY_LOG_NAME = 'iam_audit';

    /**
     * Terbitkan token baru untuk konsumen.
     * Token lama di-revoke dahulu agar satu konsumen hanya punya satu token aktif.
     *
     * @return array{plaintext: string, expires_at: string|null}
     */
    public function issueToken(SyncConsumer $consumer): array
    {
        $consumer->tokens()->delete();

        $token = $consumer->createToken(
            $this->tokenName($consumer),
            ['app:kepegawaian'],
            now()->addYear(),
        );

        $this->logAudit('sync_consumer.token_issued', $consumer);

        return [
            'plaintext' => $token->plainTextToken,
            'expires_at' => $token->accessToken->expires_at?->toIso8601String(),
        ];
    }

    /**
     * Regenerasi token: revoke seluruh token lama lalu terbitkan yang baru.
     *
     * @return array{plaintext: string, expires_at: string|null}
     */
    public function regenerateToken(SyncConsumer $consumer): array
    {
        return $this->issueToken($consumer);
    }

    /**
     * Pastikan konsumen memiliki HMAC secret. Membuatkan yang baru bila
     * belum ada (konsumen lawas) lalu mengembalikan plaintext-nya.
     */
    public function ensureHmacSecret(SyncConsumer $consumer): string
    {
        $current = $this->readHmacSecret($consumer);

        if ($current !== null) {
            return $current;
        }

        return $this->regenerateHmacSecret($consumer);
    }

    /**
     * Putar HMAC secret konsumen: secret lama langsung tidak berlaku.
     * Token Sanctum tidak tersentuh sehingga rotasi secret dan rotasi
     * token dapat dilakukan independen.
     */
    public function regenerateHmacSecret(SyncConsumer $consumer): string
    {
        $plaintext = bin2hex(random_bytes(32));

        $consumer->forceFill(['hmac_secret' => $plaintext])->save();

        $this->logAudit('sync_consumer.hmac_regenerated', $consumer);

        return $plaintext;
    }

    /**
     * Revoke seluruh token aktif milik konsumen.
     */
    public function revokeToken(SyncConsumer $consumer): void
    {
        $consumer->tokens()->delete();

        $this->logAudit('sync_consumer.token_revoked', $consumer);
    }

    private function tokenName(SyncConsumer $consumer): string
    {
        return "sync:{$consumer->slug}";
    }

    /**
     * Baca HMAC secret terdekripsi milik konsumen. Null bila belum ada
     * atau tidak dapat didekripsi (mis. APP_KEY berubah).
     */
    private function readHmacSecret(SyncConsumer $consumer): ?string
    {
        try {
            $value = $consumer->fresh()->hmac_secret;

            return is_string($value) && $value !== '' ? $value : null;
        } catch (\Throwable $e) {
            Log::warning('Gagal mendekripsi HMAC secret konsumen', [
                'consumer_slug' => $consumer->slug,
                'exception' => $e->getMessage(),
            ]);

            return null;
        }
    }

    private function logAudit(string $event, SyncConsumer $consumer): void
    {
        try {
            activity(self::ACTIVITY_LOG_NAME)
                ->performedOn($consumer)
                ->event($event)
                ->withProperties([
                    'slug' => $consumer->slug,
                ])
                ->log($event);
        } catch (\Throwable $e) {
            Log::warning('Gagal mencatat audit token konsumen', [
                'consumer_slug' => $consumer->slug,
                'exception' => $e->getMessage(),
            ]);
        }
    }
}
